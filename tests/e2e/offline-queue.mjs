// Teste de navegador (Chromium de verdade) da fila offline da súmula: entra como operador, inicia o 1º tempo com internet,
// FICA OFFLINE, lança um gol (a tela mostra o placar projetado e a fila guarda o item), VOLTA a ficar online e confere que
// a fila esvazia e o servidor registrou o gol (placar 1 x 0 depois de recarregar).
//
// Uso: node tests/e2e/offline-queue.mjs <urlBase> '<json do seed-sheet.php>'
import { chromium } from 'playwright-core';

const [baseUrl, seedJson] = process.argv.slice(2);
const seed = JSON.parse(seedJson);

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const context = await browser.newContext();
const page = await context.newPage();
page.on('pageerror', (error) => console.error(`[pageerror] ${error.message}`));
page.on('requestfailed', (request) => console.error(`[requestfailed] ${request.url()} ${request.failure()?.errorText}`));
page.on('response', (response) => response.status() >= 400 && console.error(`[http ${response.status()}] ${response.url()}`));
page.setDefaultTimeout(20000);

const fail = async (message) => {
    console.error(`FALHOU: ${message}`);
    console.error(`URL: ${page.url()}`);
    console.error(`Texto da página: ${(await page.locator('body').innerText().catch(() => '')).slice(0, 800)}`);
    await page.screenshot({ path: 'offline-queue-falha.png', fullPage: true }).catch(() => {});
    await browser.close();
    process.exit(1);
};

try {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.fill('#email', seed.email);
    await page.fill('#password', seed.password);
    const [loginResponse] = await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'POST' && new URL(response.url()).pathname === '/login'),
        page.click('button[type=submit]'),
    ]);
    console.log(`POST /login -> ${loginResponse.status()}`);
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { waitUntil: 'commit' });

    await page.goto(`${baseUrl}/painel/games/${seed.game}/sheet`);
    await page.getByTestId('scoreboard').waitFor();

    // Com internet: iniciar o 1º tempo passa pela fila e é enviado na hora.
    await page.getByRole('button', { name: /Iniciar 1º tempo/ }).click();
    await page.getByText('1º tempo em andamento').waitFor();
    await page.getByTestId('sheet-queue').waitFor({ state: 'detached' });

    // Sem internet: lança um gol do mandante.
    await context.setOffline(true);
    await page.getByText('Sem conexão').waitFor();

    const form = page.getByRole('form', { name: 'Novo lançamento' });
    const person = form.getByLabel('Pessoa');
    await person.selectOption({ index: 1 });
    await form.getByRole('button', { name: 'Lançar' }).click();

    await page.getByRole('heading', { name: /Fila de envio \(1\)/ }).waitFor();
    const projected = (await page.getByTestId('scoreboard').innerText()).replace(/\s+/g, ' ').trim();
    if (projected !== '1 x 0') await fail(`placar projetado esperado "1 x 0", veio "${projected}"`);

    // Guardado no aparelho (sem nome nem documento).
    const stored = await page.evaluate(() => Object.entries(localStorage).filter(([key]) => key.startsWith('mesa:v1:')));
    if (stored.length !== 1) await fail('a fila não foi guardada no localStorage');

    // Volta a conexão: a fila é enviada sozinha e some.
    await context.setOffline(false);
    await page.getByTestId('sheet-queue').waitFor({ state: 'detached', timeout: 30000 });

    // O servidor tem o gol: recarregar mostra 1 x 0 e a fila continua vazia.
    await page.reload();
    await page.getByTestId('scoreboard').waitFor();
    const saved = (await page.getByTestId('scoreboard').innerText()).replace(/\s+/g, ' ').trim();
    if (saved !== '1 x 0') await fail(`placar salvo esperado "1 x 0", veio "${saved}"`);
    if ((await page.evaluate(() => Object.keys(localStorage).filter((key) => key.startsWith('mesa:v1:')))).length !== 0) {
        await fail('a fila local não foi limpa depois do envio');
    }

    console.log('OK: lançamento offline enviado e registrado pelo servidor.');
    await browser.close();
} catch (error) {
    await fail(String(error));
}

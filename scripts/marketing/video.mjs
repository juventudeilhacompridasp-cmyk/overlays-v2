// Grava o vídeo curto do Modo operador da landing: `npm run marketing:video` (depois de `npm ci`).
// Usa o MESMO harness dos prints (tela real, dados fictícios) e roteiriza uns 14 s de uso: liga camadas com o mouse e com o
// teclado, passa a escalação, liga uma tela extra, mexe nos contadores e abre/fecha a edição de layout. Um cursor fictício
// aparece no vídeo. Saída: public/marketing/operador.webm (sem áudio). O poster é operador.webp (gerado por marketing:shots).
import { copyFileSync, mkdirSync, mkdtempSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { launchBrowser, out, startHarness } from './harness.mjs';

const WIDTH = 1280;
const HEIGHT = 720;
const harness = await startHarness();
const browser = await launchBrowser();
const dir = mkdtempSync(join(tmpdir(), 'operador-video-'));
mkdirSync(out, { recursive: true });

const context = await browser.newContext({ viewport: { width: 1366, height: 768 }, recordVideo: { dir, size: { width: WIDTH, height: HEIGHT } } });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (error) => errors.push(String(error).slice(0, 300)));
// Primeiros quadros já na cor do painel (sem clarão branco enquanto carrega).
await page.addInitScript(() => {
    const paint = () => document.documentElement && (document.documentElement.style.background = '#f3f4f6');

    paint();
    document.addEventListener('readystatechange', paint);
});

const pause = (ms) => page.waitForTimeout(ms);

/** Leva o cursor até o elemento (movimento suave) e clica. */
async function click(locator, hold = 650) {
    const box = await locator.first().boundingBox();

    if (!box) throw new Error('elemento não encontrado para o roteiro do vídeo');

    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 28 });
    await pause(220);
    await page.mouse.down();
    await page.mouse.up();
    await pause(hold);
}

try {
    await page.goto(`${harness.base}/?shot=operador&cursor=1`);
    await page.waitForSelector('[data-shot-ready="true"]', { state: 'attached', timeout: 30000 });
    await page.mouse.move(900, 80);
    await pause(1100);

    await click(page.getByRole('button', { name: /3 · Escalação/ }));
    for (let index = 0; index < 3; index++) await click(page.getByRole('button', { name: /Próximo/ }), 520);
    await click(page.getByRole('button', { name: /Pênaltis/ }));
    await click(page.getByRole('button', { name: 'Mais Finalizações do mandante' }), 380);
    await click(page.getByRole('button', { name: 'Mais Finalizações do mandante' }), 380);
    await click(page.getByRole('button', { name: 'Mais Escanteios do visitante' }), 500);

    // O teclado também opera: 4 liga/desliga a tabela.
    await page.keyboard.press('4');
    await pause(900);
    await page.keyboard.press('4');
    await pause(700);

    await click(page.getByRole('button', { name: /Editar layout/ }), 1500);
    await click(page.getByRole('button', { name: /Concluir layout/ }), 1400);
} finally {
    const video = page.video();
    await context.close(); // finaliza o arquivo
    const target = join(out, 'operador.webm');

    if (video) copyFileSync(await video.path(), target);

    await browser.close();
    await harness.close();

    console.log(`operador.webm  ${(statSync(target).size / 1024).toFixed(0)} KB${errors.length ? `  (erros: ${errors.join(' | ')})` : ''}`);
    process.exitCode = errors.length > 0 ? 1 : 0;
}

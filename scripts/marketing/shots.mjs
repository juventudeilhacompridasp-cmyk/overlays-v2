// Gera os prints da galeria da landing page: `npm run marketing:shots` (depois de `npm ci`).
// Sobe o Vite com um harness que renderiza as telas REAIS do produto com dados fictícios (scripts/marketing/harness),
// tira o print no Chromium (CHROMIUM_PATH opcional, como nos testes de navegador) e converte para WebP 1280 x 720 dentro do
// próprio navegador (canvas), sem dependências extras. Saída: public/marketing/{name}.webp. Rode de novo quando uma tela mudar.
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';
import { OUT_HEIGHT, OUT_WIDTH, SHOTS } from './shots.config.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const harness = join(root, 'scripts', 'marketing', 'harness');
const out = join(root, 'public', 'marketing');
const only = process.argv.slice(2);

// O Tailwind/PostCSS resolvem os caminhos a partir da raiz do projeto.
process.chdir(root);
mkdirSync(out, { recursive: true });

const server = await createServer({
    root: harness,
    configFile: false,
    plugins: [react()],
    resolve: { alias: [{ find: '@inertiajs/react', replacement: join(harness, 'inertia-stub.tsx') }, { find: '@', replacement: join(root, 'resources', 'js') }] },
    css: { postcss: root },
    server: { port: 0, host: '127.0.0.1', fs: { strict: false } },
    logLevel: 'warn',
});
await server.listen();
const base = server.resolvedUrls.local[0].replace(/\/$/, '');
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
let failed = false;

try {
    for (const shot of SHOTS.filter((item) => only.length === 0 || only.includes(item.name))) {
        const page = await browser.newPage({ viewport: { width: shot.width, height: shot.height } });
        const errors = [];
        page.on('pageerror', (error) => errors.push(String(error).slice(0, 300)));
        page.on('console', (message) => message.type() === 'error' && !message.text().includes('WebSocket') && !message.text().includes('Failed to load resource') && errors.push(message.text().slice(0, 300)));

        await page.goto(`${base}/?shot=${shot.name}`);
        try {
            await page.waitForSelector('[data-shot-ready="true"]', { state: 'attached', timeout: 15000 });
        } catch (error) {
            console.error(`${shot.name}: a tela não ficou pronta.\n${errors.join('\n') || '(sem erros no console)'}`);
            throw error;
        }
        await page.evaluate(() => document.fonts.ready);
        await page.waitForTimeout(900);

        const png = (await page.screenshot({ type: 'png' })).toString('base64');
        const webp = await page.evaluate(async ({ data, width, height }) => {
            const image = new Image();
            image.src = `data:image/png;base64,${data}`;
            await image.decode();
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            context.imageSmoothingQuality = 'high';
            context.drawImage(image, 0, 0, width, height);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.82));
            const buffer = new Uint8Array(await blob.arrayBuffer());
            let binary = '';
            for (let index = 0; index < buffer.length; index += 0x8000) binary += String.fromCharCode(...buffer.subarray(index, index + 0x8000));

            return btoa(binary);
        }, { data: png, width: OUT_WIDTH, height: OUT_HEIGHT });

        const bytes = Buffer.from(webp, 'base64');
        writeFileSync(join(out, `${shot.name}.webp`), bytes);
        console.log(`${shot.name}.webp  ${(bytes.length / 1024).toFixed(0)} KB${errors.length ? `  (erros: ${errors.join(' | ')})` : ''}`);
        failed ||= errors.length > 0;
        await page.close();
    }
} finally {
    await browser.close();
    await server.close();
}

process.exit(failed ? 1 : 0);

// Servidor Vite e navegador compartilhados pelos geradores de mídia da landing (prints e vídeo).
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { chromium } from 'playwright-core';
import { createServer } from 'vite';

export const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
export const out = join(root, 'public', 'marketing');
const harness = join(root, 'scripts', 'marketing', 'harness');

/** Sobe o harness (as telas reais com dados fictícios) e devolve a URL base e o `close`. */
export async function startHarness() {
    // O Tailwind/PostCSS resolvem os caminhos a partir da raiz do projeto.
    process.chdir(root);

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

    return { base: server.resolvedUrls.local[0].replace(/\/$/, ''), close: () => server.close() };
}

/** Chromium (CHROMIUM_PATH opcional, como nos testes de navegador). */
export const launchBrowser = () => chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });

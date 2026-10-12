import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
    ],
    // O bundle de SSR fica autocontido: o container de produção não precisa de node_modules.
    ssr: {
        noExternal: true,
    },
    // HMR dentro do Docker: o navegador acessa o Vite em localhost:5173.
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: { host: 'localhost' },
        watch: { ignored: ['**/storage/**', '**/vendor/**'] },
    },
});

/*
 * Service worker do app do mesário. Objetivo: instalar e abrir rápido, e avisar quando não há internet.
 * Por privacidade e segurança NÃO guarda páginas do painel nem respostas da API (têm dados de atletas e sessão):
 * só os arquivos versionados do front (/build/) e a página de "sem conexão".
 */
const VERSION = 'mesario-v1';
const ASSETS = 'assets-' + VERSION;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(ASSETS).then((cache) => cache.add(OFFLINE_URL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== ASSETS).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Arquivos versionados pelo Vite: o nome muda a cada build, então podem ficar guardados para sempre.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.open(ASSETS).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) return cached;

                const response = await fetch(request);
                if (response.ok) cache.put(request, response.clone());

                return response;
            }),
        );
        return;
    }

    // Páginas: sempre da rede; sem internet, mostra a página de "sem conexão" (nunca uma cópia do painel).
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    }
});

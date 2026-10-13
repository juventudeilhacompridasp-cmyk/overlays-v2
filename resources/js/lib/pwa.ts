import { useEffect, useState } from 'react';

/** Registra o service worker do app do mesário (só em HTTPS ou localhost; sem suporte, não faz nada). */
export function registerServiceWorker(): void {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    });
}

/** Navegador sem internet? (atualiza sozinho quando a conexão cai ou volta) */
export function useOnline(): boolean {
    const [online, setOnline] = useState(() => (typeof navigator === 'undefined' ? true : navigator.onLine));

    useEffect(() => {
        const update = () => setOnline(navigator.onLine);
        window.addEventListener('online', update);
        window.addEventListener('offline', update);

        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);

    return online;
}

interface InstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

/** Oferece "Instalar app" quando o navegador permite (Chrome/Android); depois de instalado, some. */
export function useInstallPrompt(): { available: boolean; install: () => Promise<void> } {
    const [event, setEvent] = useState<InstallPromptEvent | null>(null);

    useEffect(() => {
        const onPrompt = (e: Event) => {
            e.preventDefault();
            setEvent(e as InstallPromptEvent);
        };
        const onInstalled = () => setEvent(null);
        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', onPrompt);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    return {
        available: event !== null,
        install: async () => {
            if (!event) return;
            await event.prompt();
            await event.userChoice;
            setEvent(null);
        },
    };
}

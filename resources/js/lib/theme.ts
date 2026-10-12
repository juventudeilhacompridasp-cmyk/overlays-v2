import { useCallback, useEffect, useState } from 'react';

export type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'theme';

function readStored(): Theme {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY);

        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
}

/** Aplica a escolha ao <html> (a mesma regra do script inicial do app.blade.php). */
export function applyTheme(theme: Theme): void {
    const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.documentElement.classList.toggle('dark', dark);
}

/**
 * Preferência de tema (claro, escuro ou do sistema), guardada no navegador. Começa em "system" no servidor e lê o
 * valor salvo depois da montagem, para o HTML do SSR e a hidratação baterem.
 */
export function useTheme(): [Theme, (theme: Theme) => void] {
    const [theme, setThemeState] = useState<Theme>('system');

    useEffect(() => setThemeState(readStored()), []);

    useEffect(() => {
        if (theme !== 'system') {
            return;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => applyTheme('system');
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, [theme]);

    const setTheme = useCallback((next: Theme) => {
        setThemeState(next);

        try {
            window.localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // navegação privada: vale só para esta visita
        }

        applyTheme(next);
    }, []);

    return [theme, setTheme];
}

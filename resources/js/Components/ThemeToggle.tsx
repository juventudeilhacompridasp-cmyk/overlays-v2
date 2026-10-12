import { Theme, useTheme } from '@/lib/theme';

const options: { value: Theme; label: string; icon: string }[] = [
    { value: 'light', label: 'Claro', icon: 'M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4M12 16a4 4 0 100-8 4 4 0 000 8z' },
    { value: 'system', label: 'Automático (segue o dispositivo)', icon: 'M4 5h16v11H4zM8 20h8M12 16v4' },
    { value: 'dark', label: 'Escuro', icon: 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z' },
];

/** Alternador de tema (claro, automático, escuro). `onDark` = usado sobre fundo colorido (cabeçalho do portal). */
export default function ThemeToggle({ onDark = false }: { onDark?: boolean }) {
    const [theme, setTheme] = useTheme();

    return (
        <div role="group" aria-label="Tema" className={`inline-flex rounded-md p-0.5 ${onDark ? 'bg-white/20' : 'bg-gray-100'}`}>
            {options.map((option) => {
                const active = theme === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        title={option.label}
                        aria-label={option.label}
                        aria-pressed={active}
                        onClick={() => setTheme(option.value)}
                        className={`rounded p-1.5 transition ${
                            active ? (onDark ? 'bg-white/90 text-neutral-900' : 'bg-white text-emerald-700 shadow-sm') : onDark ? 'text-white/80 hover:text-white' : 'text-gray-500 hover:text-gray-800'
                        }`}
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                            <path strokeLinecap="round" strokeLinejoin="round" d={option.icon} />
                        </svg>
                    </button>
                );
            })}
        </div>
    );
}

import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Toast = { id: number; kind: 'success' | 'error'; text: string };

const DURATION: Record<Toast['kind'], number> = { success: 5000, error: 8000 };

let nextId = 1;

/**
 * Mostra as mensagens de retorno (flash `success`/`error`) como avisos flutuantes no canto
 * superior direito. Cada resposta do servidor com mensagem gera um aviso novo; some sozinho
 * ou ao clicar no X.
 */
export default function FlashToasts() {
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState<Toast[]>([]);

    useEffect(() => {
        const incoming: Toast[] = [];
        if (flash?.success) incoming.push({ id: nextId++, kind: 'success', text: flash.success });
        if (flash?.error) incoming.push({ id: nextId++, kind: 'error', text: flash.error });
        if (incoming.length === 0) return;

        setToasts((current) => [...current, ...incoming]);
        // Sem limpeza ao trocar `flash`: o aviso deve sobreviver à próxima resposta (que vem sem mensagem).
        incoming.forEach((toast) =>
            window.setTimeout(() => setToasts((current) => current.filter((t) => t.id !== toast.id)), DURATION[toast.kind]),
        );
    }, [flash]);

    if (toasts.length === 0) return null;

    return (
        <div className="pointer-events-none fixed right-4 top-4 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-2 print:hidden">
            {toasts.map((toast) => {
                const success = toast.kind === 'success';

                return (
                    <div
                        key={toast.id}
                        role={success ? 'status' : 'alert'}
                        className={`pointer-events-auto flex items-start gap-3 rounded-md px-4 py-3 text-sm shadow-lg ring-1 ${
                            success ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-red-50 text-red-800 ring-red-200'
                        }`}
                    >
                        <span className="flex-1">{toast.text}</span>
                        <button
                            type="button"
                            aria-label="Fechar aviso"
                            onClick={() => setToasts((current) => current.filter((t) => t.id !== toast.id))}
                            className="-mr-1 shrink-0 rounded p-0.5 opacity-70 hover:opacity-100"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>
                );
            })}
        </div>
    );
}

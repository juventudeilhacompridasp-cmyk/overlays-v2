import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const kinds: Record<string, { accent: string; icon: string }> = {
    goal: { accent: '#16a34a', icon: '⚽' },
    own_goal: { accent: '#16a34a', icon: '⚽' },
    yellow_card: { accent: '#eab308', icon: '🟨' },
    red_card: { accent: '#dc2626', icon: '🟥' },
    blue_card: { accent: '#2563eb', icon: '🟦' },
    substitution: { accent: '#475569', icon: '🔁' },
};

export default function LowerThird({ token, state, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { banner } = useOverlay(token, state, realtime);
    // Mantém a última faixa na tela durante a animação de saída.
    const [last, setLast] = useState(banner);
    const [shown, setShown] = useState(false);

    useEffect(() => {
        if (banner) {
            setLast(banner);
            const frame = requestAnimationFrame(() => setShown(true));

            return () => cancelAnimationFrame(frame);
        }

        setShown(false);
    }, [banner]);

    const style = last ? kinds[last.kind] ?? kinds.goal : kinds.goal;

    return (
        <div className="fixed inset-x-0 bottom-0 p-12 font-sans text-white" style={{ textShadow: '0 1px 2px rgba(0,0,0,.5)' }}>
            <Head title="Lances" />
            {last && (
                <div
                    data-testid="overlay-lowerthird"
                    data-visible={shown}
                    className="inline-flex items-stretch overflow-hidden rounded-lg bg-gray-900/95 shadow-2xl transition-all duration-500"
                    style={{ opacity: shown ? 1 : 0, transform: shown ? 'translateX(0)' : 'translateX(-3rem)' }}
                >
                    <div className="flex items-center px-5 text-4xl" style={{ backgroundColor: style.accent }}>
                        {style.icon}
                    </div>
                    <div className="px-6 py-3">
                        <div className="flex items-baseline gap-3">
                            <span className="text-3xl font-extrabold tracking-wide">{last.title}</span>
                            {last.minute !== null && <span className="text-xl text-gray-300">{last.minute}'</span>}
                        </div>
                        {last.subtitle && <div className="text-2xl font-semibold">{last.subtitle}</div>}
                        {last.team && (
                            <div className="mt-1 inline-block rounded px-2 text-sm font-bold uppercase" style={{ backgroundColor: last.color ?? '#6b7280' }}>
                                {last.team}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

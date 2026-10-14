import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const kinds: Record<string, { accent: string; icon: string; mark: string }> = {
    goal: { accent: '#16a34a', icon: '⚽', mark: 'GOL' },
    own_goal: { accent: '#16a34a', icon: '⚽', mark: 'GC' },
    yellow_card: { accent: '#eab308', icon: '🟨', mark: 'CA' },
    red_card: { accent: '#dc2626', icon: '🟥', mark: 'CV' },
    blue_card: { accent: '#2563eb', icon: '🟦', mark: 'CAZ' },
    substitution: { accent: '#475569', icon: '🔁', mark: 'SUB' },
};

export default function LowerThird({ token, state, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { banner, state: current } = useOverlay(token, state, realtime);
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

    // Comando de ocultar os lances: nada fica na tela, nem a faixa que estava saindo.
    if (!current.layers.lower_third) {
        return <Head title="Lances" />;
    }

    const style = last ? kinds[last.kind] ?? kinds.goal : kinds.goal;
    const appearance = current.appearance;
    const studio = appearance.preset === 'studio';
    const duration = appearance.motion === 'none' ? 0 : appearance.motion === 'subtle' ? 300 : 500;
    const hiddenTransform = appearance.motion === 'dynamic' ? 'translate3d(-3rem, 1rem, 0) scale(.98)' : 'translate3d(0, 0, 0)';

    return (
        <div className="fixed inset-x-0 bottom-0 p-12 font-sans" style={{ ...overlayStyle(appearance), color: appearance.text_color, textShadow: '0 1px 2px rgba(0,0,0,.55)' }}>
            <Head title="Lances" />
            {last && (
                <div style={{ transform: `scale(${appearance.scale})`, transformOrigin: 'bottom left' }}>
                    <div
                        data-testid="overlay-lowerthird"
                        data-visible={shown}
                        className={`inline-flex items-stretch overflow-hidden border border-white/15 shadow-2xl ${studio ? 'rounded-sm' : 'rounded-2xl'}`}
                        style={{
                            background: studio ? `${appearance.background_color}f7` : `linear-gradient(135deg, ${appearance.background_color}f5, ${appearance.background_color}df)`,
                            opacity: shown ? 1 : 0,
                            transform: shown ? 'translate3d(0, 0, 0) scale(1)' : hiddenTransform,
                            transition: `opacity ${duration}ms ease, transform ${duration}ms cubic-bezier(.16,1,.3,1)`,
                        }}
                    >
                        <div className={`relative flex items-center px-5 ${studio ? 'text-lg font-black tracking-[0.16em]' : 'text-4xl'}`} style={{ backgroundColor: style.accent }}>
                            <span className="absolute inset-x-0 top-0 h-0.5 bg-white/50" />
                            {studio ? style.mark : style.icon}
                        </div>
                        <div className={`relative px-6 py-3 ${studio ? 'min-w-[28rem]' : ''}`}>
                            {studio && <span className="absolute inset-x-0 top-0 h-[3px]" style={{ backgroundColor: appearance.accent_color }} />}
                            <div className="flex items-baseline gap-3">
                                <span className="text-3xl font-black tracking-[0.06em]">{last.title}</span>
                                {last.minute !== null && <span className="text-xl text-white/60">{last.minute}'</span>}
                            </div>
                            {last.subtitle && <div className="text-2xl font-semibold">{last.subtitle}</div>}
                            {last.team && (
                                <div className={`mt-1 inline-block px-2 text-sm font-bold uppercase ${studio ? 'rounded-sm' : 'rounded'}`} style={{ backgroundColor: last.color ?? '#6b7280' }}>
                                    {last.team}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

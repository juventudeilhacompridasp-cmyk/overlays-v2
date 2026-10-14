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
                        data-preset={appearance.preset}
                        className={`inline-flex items-stretch overflow-hidden border border-white/15 shadow-2xl ${studio ? 'min-w-[36rem] rounded-md border-t-4' : 'rounded-2xl'}`}
                        style={{
                            background: studio ? `linear-gradient(90deg, ${appearance.background_color}fa, ${appearance.background_color}e8)` : `linear-gradient(135deg, ${appearance.background_color}f5, ${appearance.background_color}df)`,
                            borderTopColor: studio ? style.accent : undefined,
                            opacity: shown ? 1 : 0,
                            transform: shown ? 'translate3d(0, 0, 0) scale(1)' : hiddenTransform,
                            transition: `opacity ${duration}ms ease, transform ${duration}ms cubic-bezier(.16,1,.3,1)`,
                        }}
                    >
                        {studio ? (
                            <>
                                <div className="grid w-24 place-items-center bg-black/25 text-lg font-black tracking-[0.12em]" style={{ color: style.accent }}>{style.mark}</div>
                                <div className="flex flex-1 items-center justify-between gap-8 px-6 py-4">
                                    <div>
                                        <div className="text-[10px] font-bold uppercase tracking-[0.28em] text-white/45">Lance da partida</div>
                                        <div className="mt-0.5 text-3xl font-black uppercase tracking-[0.04em]">{last.title}</div>
                                        {last.subtitle && <div className="text-xl font-semibold text-white/80">{last.subtitle}</div>}
                                    </div>
                                    <div className="flex items-center gap-3">
                                        {last.team && <div className="border-l-2 pl-3 text-sm font-black uppercase tracking-wide" style={{ borderColor: last.color ?? style.accent }}>{last.team}</div>}
                                        {last.minute !== null && <div className="min-w-16 bg-white/10 px-3 py-2 text-center text-2xl font-black tabular-nums" style={{ color: style.accent }}>{last.minute}'</div>}
                                    </div>
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="relative flex items-center px-5 text-4xl" style={{ backgroundColor: style.accent }}>
                                    <span className="absolute inset-x-0 top-0 h-0.5 bg-white/50" />
                                    {style.icon}
                                </div>
                                <div className="px-6 py-3">
                                    <div className="flex items-baseline gap-3">
                                        <span className="text-3xl font-black tracking-[0.06em]">{last.title}</span>
                                        {last.minute !== null && <span className="text-xl text-white/60">{last.minute}'</span>}
                                    </div>
                                    {last.subtitle && <div className="text-2xl font-semibold">{last.subtitle}</div>}
                                    {last.team && (
                                        <div className="mt-1 inline-block rounded px-2 text-sm font-bold uppercase" style={{ backgroundColor: last.color ?? '#6b7280' }}>
                                            {last.team}
                                        </div>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

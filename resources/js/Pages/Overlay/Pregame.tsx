import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** hh:mm:ss (ou mm:ss) até o início; "AGORA" quando já passou. */
export function countdownLabel(remainingSeconds: number): string {
    if (remainingSeconds <= 0) return 'AGORA';
    const hours = Math.floor(remainingSeconds / 3600);
    const minutes = Math.floor((remainingSeconds % 3600) / 60);
    const seconds = remainingSeconds % 60;
    const two = (value: number) => String(value).padStart(2, '0');

    return hours > 0 ? `${hours}:${two(minutes)}:${two(seconds)}` : `${two(minutes)}:${two(seconds)}`;
}

/** Cartela de abertura do jogo: escudos, competição, local, horário e contagem regressiva para o início. */
export default function Pregame({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
    const game = state.game;
    const appearance = state.appearance;
    const [now, setNow] = useState(() => Date.now() - skew);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now() - skew), 1000);

        return () => window.clearInterval(timer);
    }, [skew]);

    const visible = state.screens.pregame && game !== null && game.home !== null && game.away !== null;
    const start = game?.scheduled_at ? new Date(game.scheduled_at) : null;
    const remaining = start ? Math.round((start.getTime() - now) / 1000) : null;
    const started = game !== null && game.status !== 'scheduled';

    return (
        <div className="fixed inset-0 flex items-center justify-center font-sans transition-opacity duration-500" style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 2px 10px rgba(0,0,0,.6)' }} data-testid="overlay-pregame" data-visible={visible}>
            <Head title="Pré-jogo" />
            {visible && game && game.home && game.away && (
                <div style={{ transform: `scale(${appearance.scale})` }}>
                    <div className="w-[70rem] overflow-hidden rounded-3xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(145deg, ${appearance.background_color}f5, ${appearance.background_color}dd)` }}>
                        <div className="px-10 py-3 text-center text-2xl font-black uppercase tracking-[0.18em]" style={{ backgroundColor: appearance.accent_color }}>
                            {[game.competition, game.division, game.stage].filter(Boolean).join(' · ')}
                        </div>
                        <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-8 px-12 py-10">
                            {[game.home, game.away].map((team, index) => (
                                <div key={index} className={`flex flex-col items-center gap-4 ${index === 1 ? 'order-3' : 'order-1'}`}>
                                    <span className="grid h-44 w-44 place-items-center rounded-full bg-white/95 p-4 shadow-inner">
                                        {team.logo_url ? <img src={team.logo_url} alt="" className="h-full w-full object-contain" /> : <span className="text-5xl font-black" style={{ color: team.color ?? appearance.accent_color }}>{(team.short_name || team.name).slice(0, 3).toUpperCase()}</span>}
                                    </span>
                                    <span className="text-center text-4xl font-black uppercase tracking-wide">{team.name}</span>
                                    <span className="h-1.5 w-40 rounded" style={{ backgroundColor: team.color ?? appearance.accent_color }} />
                                </div>
                            ))}
                            <div className="order-2 text-center">
                                <div className="text-7xl font-black text-white/40">×</div>
                                {start && (
                                    <div className="mt-3 text-2xl font-bold uppercase tracking-wider text-white/80">
                                        {start.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })} · {start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                    </div>
                                )}
                                {game.venue && <div className="mt-1 text-xl text-white/70">{game.venue}</div>}
                            </div>
                        </div>
                        {remaining !== null && !started && (
                            <div className="flex items-center justify-center gap-4 bg-black/35 px-10 py-4" data-testid="pregame-countdown">
                                <span className="text-xl font-bold uppercase tracking-[0.2em] text-white/70">Bola rolando em</span>
                                <span className="text-6xl font-black tabular-nums" style={{ color: appearance.accent_color }}>{countdownLabel(remaining)}</span>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

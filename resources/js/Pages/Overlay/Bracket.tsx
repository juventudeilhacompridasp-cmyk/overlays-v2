import { Realtime } from '@/lib/echo';
import { BracketSide, OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';

function Side({ side, accent }: { side: BracketSide; accent: string }) {
    return (
        <div className={`flex items-center gap-2 px-3 py-1.5 ${side.winner ? 'font-black' : 'text-white/75'}`} style={side.winner ? { backgroundColor: `${accent}55` } : undefined}>
            {side.logo ? <img src={side.logo} alt="" className="h-6 w-6 flex-none object-contain" /> : <span className="h-6 w-6 flex-none" />}
            <span className="flex-1 truncate text-lg uppercase">{side.short || side.name}</span>
            <span className="min-w-[1.5rem] text-right text-xl tabular-nums">{side.score ?? '–'}</span>
        </div>
    );
}

/** Chaveamento do mata-mata: uma coluna por rodada, com placar agregado, vencedor em destaque e o campeão. */
export default function Bracket({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const bracket = state.bracket;
    const appearance = state.appearance;
    const visible = state.screens.bracket && bracket !== null;

    return (
        <div className="fixed inset-0 flex items-center justify-center font-sans transition-opacity duration-500" style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 4px rgba(0,0,0,.6)' }} data-testid="overlay-bracket" data-visible={visible}>
            <Head title="Chaveamento" />
            {visible && bracket && (
                <div style={{ transform: `scale(${appearance.scale})` }}>
                    <div className="overflow-hidden rounded-3xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(145deg, ${appearance.background_color}f5, ${appearance.background_color}dd)` }}>
                        <div className="px-10 py-3 text-center text-2xl font-black uppercase tracking-[0.2em]" style={{ backgroundColor: appearance.accent_color }}>{bracket.title}</div>
                        <div className="flex items-stretch gap-8 px-8 py-8">
                            {bracket.rounds.map((round) => (
                                <div key={round.label} className="flex w-60 flex-col">
                                    <div className="mb-3 text-center text-sm font-bold uppercase tracking-[0.2em] text-white/60">{round.label}</div>
                                    <div className="flex flex-1 flex-col justify-around gap-4">
                                        {round.ties.map((tie, index) => (
                                            <div key={index} className={`overflow-hidden rounded-lg border ${tie.live ? 'border-red-400' : 'border-white/15'} bg-black/30`}>
                                                <Side side={tie.a} accent={appearance.accent_color} />
                                                <div className="h-px bg-white/10" />
                                                <Side side={tie.b} accent={appearance.accent_color} />
                                                {tie.penalties && <div className="bg-black/30 px-3 py-0.5 text-center text-xs text-white/70">Pênaltis {tie.penalties.a} × {tie.penalties.b}</div>}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                            {bracket.champion && (
                                <div className="flex w-56 flex-col items-center justify-center text-center" data-testid="bracket-champion">
                                    <div className="mb-2 text-sm font-bold uppercase tracking-[0.2em] text-white/60">Campeão</div>
                                    <div className="text-3xl font-black uppercase" style={{ color: appearance.accent_color }}>🏆 {bracket.champion}</div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

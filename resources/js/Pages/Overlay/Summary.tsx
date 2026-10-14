import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { scorerLines } from '@/lib/scorerLines';
import { Head } from '@inertiajs/react';

/** Resumo do jogo (intervalo ou fim): placar, autores dos gols, cartões (se liberados) e craque do jogo. */
export default function Summary({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const game = state.game;
    const summary = state.summary;
    const appearance = state.appearance;
    const visible = state.screens.summary && game !== null && game.home !== null && game.away !== null && summary !== null;

    return (
        <div className="fixed inset-0 flex items-center justify-center font-sans transition-opacity duration-500" style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 6px rgba(0,0,0,.6)' }} data-testid="overlay-summary" data-visible={visible}>
            <Head title="Resumo do jogo" />
            {visible && game && game.home && game.away && summary && (
                <div style={{ transform: `scale(${appearance.scale})` }}>
                    <div className="w-[60rem] overflow-hidden rounded-3xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(145deg, ${appearance.background_color}f5, ${appearance.background_color}dd)` }}>
                        <div className="px-10 py-3 text-center text-2xl font-black uppercase tracking-[0.2em]" style={{ backgroundColor: appearance.accent_color }}>{summary.headline}</div>
                        <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-6 px-10 py-8">
                            {[game.home, game.away].map((team, index) => (
                                <div key={index} className={`flex items-center gap-4 ${index === 1 ? 'order-3 flex-row-reverse text-right' : 'order-1'}`}>
                                    <span className="grid h-24 w-24 flex-none place-items-center rounded-full bg-white/95 p-2">{team.logo_url && <img src={team.logo_url} alt="" className="h-full w-full object-contain" />}</span>
                                    <span className="text-3xl font-black uppercase tracking-wide">{team.name}</span>
                                </div>
                            ))}
                            <div className="order-2 text-center text-8xl font-black tabular-nums" data-testid="summary-score">{game.home_score} <span className="text-white/35">×</span> {game.away_score}</div>
                        </div>
                        {game.score_model === 'goals' && game.goals.length > 0 && (
                            <div className="grid grid-cols-2 gap-8 border-t border-white/10 px-10 py-5 text-2xl" data-testid="summary-goals">
                                {(['home', 'away'] as const).map((side) => (
                                    <div key={side} className={side === 'away' ? 'text-right' : ''}>
                                        {scorerLines(game.goals, side).map((line) => <div key={line}>⚽ {line}</div>)}
                                    </div>
                                ))}
                            </div>
                        )}
                        {summary.cards && summary.cards.length > 0 && (
                            <div className="grid grid-cols-2 gap-8 border-t border-white/10 px-10 py-4 text-xl text-white/85" data-testid="summary-cards">
                                {(['home', 'away'] as const).map((side) => (
                                    <div key={side} className={side === 'away' ? 'text-right' : ''}>
                                        {summary.cards!.filter((card) => card.side === side).map((card, index) => (
                                            <div key={index}>{card.kind === 'red_card' ? '🟥' : '🟨'} {card.player ?? 'Jogador'}{card.minute !== null ? ` ${card.minute}'` : ''}</div>
                                        ))}
                                    </div>
                                ))}
                            </div>
                        )}
                        {summary.mvp && (
                            <div className="flex items-center justify-center gap-4 bg-black/35 px-10 py-4" data-testid="summary-mvp">
                                <span className="text-lg font-bold uppercase tracking-[0.2em] text-white/70">Craque do jogo</span>
                                <span className="text-4xl font-black uppercase" style={{ color: appearance.accent_color }}>{summary.mvp.shirt !== null ? `${summary.mvp.shirt} · ` : ''}{summary.mvp.name}</span>
                                {summary.mvp.team && <span className="text-xl text-white/70">{summary.mvp.team}</span>}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

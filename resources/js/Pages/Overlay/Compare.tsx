import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';

const RESULT_COLORS: Record<string, string> = { V: '#16a34a', E: '#9ca3af', D: '#dc2626' };

function Form({ results }: { results: string[] }) {
    return (
        <div className="flex gap-1.5">
            {results.length === 0 && <span className="text-lg text-white/40">—</span>}
            {results.map((result, index) => (
                <span key={index} className="grid h-9 w-9 place-items-center rounded-md text-lg font-black text-white" style={{ backgroundColor: RESULT_COLORS[result] ?? '#6b7280' }}>{result}</span>
            ))}
        </div>
    );
}

/** Confronto direto como barra proporcional (vitórias do mandante, empates, vitórias do visitante). */
function HeadToHead({ summary }: { summary: { home_wins: number; draws: number; away_wins: number } }) {
    const total = Math.max(1, summary.home_wins + summary.draws + summary.away_wins);
    const part = (value: number) => ({ width: `${(value / total) * 100}%` });

    return (
        <div>
            <div className="flex h-4 overflow-hidden rounded-full bg-white/10">
                <span style={{ ...part(summary.home_wins), backgroundColor: '#38bdf8' }} />
                <span style={{ ...part(summary.draws), backgroundColor: '#9ca3af' }} />
                <span style={{ ...part(summary.away_wins), backgroundColor: '#f59e0b' }} />
            </div>
            <div className="mt-1 flex justify-between text-xl font-bold tabular-nums">
                <span>{summary.home_wins} V</span>
                <span className="text-white/60">{summary.draws} E</span>
                <span>{summary.away_wins} V</span>
            </div>
        </div>
    );
}

/** Comparativo de equipes: posição na tabela, forma recente e confronto direto. Só números públicos. */
export default function Compare({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const game = state.game;
    const compare = state.compare;
    const appearance = state.appearance;
    const visible = state.screens.compare && game !== null && game.home !== null && game.away !== null && compare !== null;

    return (
        <div className="fixed inset-0 flex items-center justify-center font-sans transition-opacity duration-500" style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 6px rgba(0,0,0,.6)' }} data-testid="overlay-compare" data-visible={visible}>
            <Head title="Comparativo" />
            {visible && game && game.home && game.away && compare && (
                <div style={{ transform: `scale(${appearance.scale})` }}>
                    <div className="w-[64rem] overflow-hidden rounded-3xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(145deg, ${appearance.background_color}f5, ${appearance.background_color}dd)` }}>
                        <div className="px-10 py-3 text-center text-2xl font-black uppercase tracking-[0.18em]" style={{ backgroundColor: appearance.accent_color }}>Comparativo</div>
                        <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-6 px-12 py-8">
                            {[game.home, game.away].map((team, index) => (
                                <div key={index} className={`flex items-center gap-4 ${index === 1 ? 'order-3 flex-row-reverse text-right' : 'order-1'}`}>
                                    <span className="grid h-20 w-20 flex-none place-items-center rounded-full bg-white/95 p-2">{team.logo_url && <img src={team.logo_url} alt="" className="h-full w-full object-contain" />}</span>
                                    <span className="text-4xl font-black uppercase tracking-wide">{team.name}</span>
                                </div>
                            ))}
                            <span className="order-2 text-5xl font-black text-white/40">×</span>
                        </div>
                        <div className="space-y-6 px-12 pb-10">
                            {compare.standings && (
                                <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-6" data-testid="compare-standings">
                                    {[compare.standings.home, compare.standings.away].map((row, index) => (
                                        <div key={index} className={`text-3xl font-black tabular-nums ${index === 1 ? 'order-3 text-right' : 'order-1'}`}>
                                            {row ? <>{row.position}º <span className="text-xl font-semibold text-white/60">· {row.points} pts · {row.played} j</span></> : '—'}
                                        </div>
                                    ))}
                                    <span className="order-2 text-lg font-bold uppercase tracking-widest text-white/60">{compare.standings.title}</span>
                                </div>
                            )}
                            {compare.form && (
                                <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-6" data-testid="compare-form">
                                    <div className="order-1"><Form results={compare.form.home} /></div>
                                    <span className="order-2 text-lg font-bold uppercase tracking-widest text-white/60">Últimos 5</span>
                                    <div className="order-3 flex justify-end"><Form results={compare.form.away} /></div>
                                </div>
                            )}
                            {compare.head_to_head && (
                                <div data-testid="compare-h2h">
                                    <div className="mb-1 text-center text-lg font-bold uppercase tracking-widest text-white/60">Confronto direto</div>
                                    <HeadToHead summary={compare.head_to_head} />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

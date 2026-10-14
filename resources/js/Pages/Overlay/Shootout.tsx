import { PenaltyDots } from '@/Components/Overlay/PenaltyDots';
import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { shootoutCaption, shootoutRows } from '@/lib/shootout';
import { Head } from '@inertiajs/react';

/** Disputa de pênaltis (tela extra): placar da disputa e as cobranças de cada equipe, uma bolinha por cobrança. */
export default function Shootout({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const game = state.game;
    const appearance = state.appearance;
    const visible = state.screens.shootout === true && game !== null && game.home !== null && game.away !== null;
    const shootout = game?.shootout ?? null;
    const view = shootout ?? { home: [], away: [], goals: { home: 0, away: 0 }, first: null, next: null, decided: false, winner: null, rounds: 5, by_kick: true };
    const rows = shootoutRows(view);

    return (
        <div className="fixed inset-0 flex items-center justify-center font-sans transition-opacity duration-500" style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 6px rgba(0,0,0,.6)' }} data-testid="overlay-shootout" data-visible={visible}>
            <Head title="Disputa de pênaltis" />
            {visible && game && game.home && game.away && (
                <div style={{ transform: `scale(${appearance.scale})` }}>
                    <div className="w-[62rem] overflow-hidden rounded-3xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(145deg, ${appearance.background_color}f5, ${appearance.background_color}dd)` }}>
                        <div className="px-10 py-3 text-center text-2xl font-black uppercase tracking-[0.2em]" style={{ backgroundColor: appearance.accent_color }}>Disputa de pênaltis</div>
                        <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-6 px-10 pb-2 pt-8">
                            {[game.home, game.away].map((team, index) => (
                                <div key={index} className={`flex items-center gap-4 ${index === 1 ? 'order-3 flex-row-reverse text-right' : 'order-1'}`}>
                                    <span className="grid h-24 w-24 flex-none place-items-center rounded-full bg-white/95 p-2">{team.logo_url && <img src={team.logo_url} alt="" className="h-full w-full object-contain" />}</span>
                                    <span className="text-3xl font-black uppercase tracking-wide">{team.name}</span>
                                </div>
                            ))}
                            <div className="order-2 text-center text-8xl font-black tabular-nums" data-testid="shootout-score">{view.goals.home} <span className="text-white/35">×</span> {view.goals.away}</div>
                        </div>
                        <div className="space-y-3 px-10 py-5">
                            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-6">
                                <div className="order-1"><PenaltyDots dots={rows.home} size={44} label="Cobranças do mandante" /></div>
                                <span className="order-2 w-24" />
                                <div className="order-3 text-right"><PenaltyDots dots={rows.away} size={44} align="right" label="Cobranças do visitante" /></div>
                            </div>
                        </div>
                        <div className="bg-black/35 px-10 py-4 text-center text-2xl font-black uppercase tracking-[0.18em]" style={{ color: view.decided ? appearance.accent_color : undefined }} data-testid="shootout-caption">
                            {shootoutCaption(view, { home: game.home.name, away: game.away.name })}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

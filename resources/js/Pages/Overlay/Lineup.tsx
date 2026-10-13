import { Realtime } from '@/lib/echo';
import { LineupSide, OverlayState, useOverlay } from '@/lib/overlay';
import { overlayMotion, overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';

function Column({ side, background, accent }: { side: LineupSide; background: string; accent: string }) {
    return (
        <div className="w-96 overflow-hidden rounded-2xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(160deg, ${background}f5, ${background}df)` }}>
            <div className="relative flex items-center gap-3 px-5 py-3">
                <span className="absolute inset-x-0 bottom-0 h-1" style={{ backgroundColor: side.color ?? accent }} />
                {side.logo_url && <span className="grid h-12 w-12 place-items-center rounded-full bg-white/95 p-1"><img src={side.logo_url} alt="" className="h-10 w-10 object-contain" /></span>}
                <span className="truncate text-2xl font-black uppercase tracking-wide">{side.name}</span>
                {side.formation && <span className="ml-auto text-lg font-black" style={{ color: accent }}>{side.formation}</span>}
            </div>
            <ol className="px-5 py-3 text-xl">
                {side.starters.map((player, index) => (
                    <li key={index} className="flex gap-4 py-0.5">
                        <span className="w-8 text-right font-bold tabular-nums" style={{ color: accent }}>{player.shirt ?? ''}</span>
                        <span className="truncate font-semibold">
                            {player.name}
                            {player.captain && <span className="ml-2 text-base font-bold text-amber-300">(C)</span>}
                        </span>
                        {player.position && <span className="ml-auto text-sm font-semibold uppercase text-gray-300">{player.position}</span>}
                    </li>
                ))}
            </ol>
            {side.bench.length > 0 && (
                <div className="border-t border-white/10 px-5 py-2 text-sm text-gray-300">
                    <span className="font-semibold uppercase">Reservas: </span>
                    {side.bench.map((player) => `${player.shirt ?? ''} ${player.name}${player.captain ? ' (C)' : ''}`.trim()).join(' · ')}
                </div>
            )}
            {side.coach && (
                <div className="border-t border-white/10 px-5 py-2 text-sm">
                    <span className="font-semibold uppercase text-gray-300">Técnico: </span>
                    {side.coach}
                </div>
            )}
        </div>
    );
}

export default function Lineup({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const visible = state.lineup !== null;

    return (
        <div className="fixed inset-0 flex items-center justify-center gap-10 font-sans transition-opacity duration-500" style={{ ...overlayStyle(state.appearance), color: state.appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 2px rgba(0,0,0,.55)' }} data-testid="overlay-lineup" data-visible={visible}>
            <Head title="Escalação" />
            {state.lineup && (
                <div style={{ transform: `scale(${state.appearance.scale})` }}>
                    <div className={`flex gap-10 ${overlayMotion(state.appearance.motion)}`}>
                        <Column side={state.lineup.home} background={state.appearance.background_color} accent={state.appearance.accent_color} />
                        <Column side={state.lineup.away} background={state.appearance.background_color} accent={state.appearance.accent_color} />
                    </div>
                </div>
            )}
        </div>
    );
}

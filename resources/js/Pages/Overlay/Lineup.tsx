import { Realtime } from '@/lib/echo';
import { LineupSide, OverlayState, useOverlay } from '@/lib/overlay';
import { Head } from '@inertiajs/react';

function Column({ side }: { side: LineupSide }) {
    return (
        <div className="w-96 overflow-hidden rounded-lg bg-gray-900/95 shadow-2xl">
            <div className="flex items-center gap-3 px-5 py-3" style={{ borderBottom: `5px solid ${side.color ?? '#9ca3af'}` }}>
                {side.logo_url && <img src={side.logo_url} alt="" className="h-10 w-10 object-contain" />}
                <span className="truncate text-2xl font-extrabold uppercase">{side.name}</span>
                {side.formation && <span className="ml-auto text-lg font-bold text-emerald-400">{side.formation}</span>}
            </div>
            <ol className="px-5 py-3 text-xl">
                {side.starters.map((player, index) => (
                    <li key={index} className="flex gap-4 py-0.5">
                        <span className="w-8 text-right font-bold tabular-nums text-emerald-400">{player.shirt ?? ''}</span>
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
        <div className="fixed inset-0 flex items-center justify-center gap-10 font-sans text-white transition-opacity duration-500" style={{ opacity: visible ? 1 : 0, textShadow: '0 1px 2px rgba(0,0,0,.5)' }} data-testid="overlay-lineup" data-visible={visible}>
            <Head title="Escalação" />
            {state.lineup && (
                <>
                    <Column side={state.lineup.home} />
                    <Column side={state.lineup.away} />
                </>
            )}
        </div>
    );
}

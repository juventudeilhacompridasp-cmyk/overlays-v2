import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { Head } from '@inertiajs/react';

export default function Standings({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const table = state.standings;
    const visible = table !== null;

    return (
        <div className="fixed inset-y-0 right-0 flex items-center p-10 font-sans text-white transition-opacity duration-500" style={{ opacity: visible ? 1 : 0, textShadow: '0 1px 2px rgba(0,0,0,.5)' }} data-testid="overlay-standings" data-visible={visible}>
            <Head title="Tabela" />
            {table && (
                <div className="w-[34rem] overflow-hidden rounded-lg bg-gray-900/95 shadow-2xl">
                    <div className="bg-emerald-600 px-5 py-2 text-xl font-extrabold uppercase">{table.title}</div>
                    <table className="w-full text-lg">
                        <thead className="text-xs uppercase text-gray-400">
                            <tr>
                                <th className="w-10 px-3 py-1 text-right">#</th>
                                <th className="px-2 py-1 text-left">Equipe</th>
                                <th className="px-2 py-1 text-center">P</th>
                                <th className="px-2 py-1 text-center">J</th>
                                <th className="px-3 py-1 text-center">SG</th>
                            </tr>
                        </thead>
                        <tbody>
                            {table.rows.map((row) => (
                                <tr key={row.team} className={row.highlight ? 'bg-emerald-500/25 font-bold' : ''}>
                                    <td className="px-3 py-1 text-right tabular-nums text-gray-300">{row.position}</td>
                                    <td className="px-2 py-1">
                                        <span className="flex items-center gap-2">
                                            {row.logo_url && <img src={row.logo_url} alt="" className="h-6 w-6 object-contain" />}
                                            <span className="truncate">{row.team}</span>
                                        </span>
                                    </td>
                                    <td className="px-2 py-1 text-center font-bold tabular-nums">{row.points}</td>
                                    <td className="px-2 py-1 text-center tabular-nums">{row.played}</td>
                                    <td className="px-3 py-1 text-center tabular-nums">{row.goal_difference}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

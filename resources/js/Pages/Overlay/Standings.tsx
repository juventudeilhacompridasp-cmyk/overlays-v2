import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayMotion, overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';

export default function Standings({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const table = state.standings;
    const visible = table !== null;
    const studio = state.appearance.preset === 'studio';

    return (
        <div className="fixed inset-y-0 right-0 flex items-center p-10 font-sans transition-opacity duration-500" style={{ ...overlayStyle(state.appearance), color: state.appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 2px rgba(0,0,0,.55)' }} data-testid="overlay-standings" data-visible={visible} data-preset={state.appearance.preset}>
            <Head title="Tabela" />
            {table && (
                <div style={{ transform: `scale(${state.appearance.scale})`, transformOrigin: 'right center' }}>
                    <div className={`w-[34rem] overflow-hidden border border-white/15 shadow-2xl ${studio ? 'rounded-md border-t-4' : 'rounded-2xl'} ${overlayMotion(state.appearance.motion)}`} style={{ background: studio ? `linear-gradient(180deg, ${state.appearance.background_color}fa, ${state.appearance.background_color}e8)` : `linear-gradient(160deg, ${state.appearance.background_color}f5, ${state.appearance.background_color}df)`, borderTopColor: studio ? state.appearance.accent_color : undefined }}>
                        <div className={`px-5 text-xl font-black uppercase ${studio ? 'border-b border-white/10 bg-black/20 py-4 tracking-[0.12em]' : 'py-2 tracking-wide'}`} style={studio ? { color: state.appearance.accent_color } : { backgroundColor: state.appearance.accent_color }}>{table.title}</div>
                        <table className="w-full text-lg">
                        <thead className={`text-xs uppercase text-gray-400 ${studio ? 'border-b border-white/10 bg-black/10 tracking-[0.14em]' : ''}`}>
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
                                <tr key={row.team} className={`${row.highlight ? 'font-bold' : ''} ${studio ? 'border-b border-white/5 last:border-0' : ''}`} style={row.highlight ? { backgroundColor: `${state.appearance.accent_color}3f`, boxShadow: studio ? `inset 4px 0 ${state.appearance.accent_color}` : undefined } : undefined}>
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
                </div>
            )}
        </div>
    );
}

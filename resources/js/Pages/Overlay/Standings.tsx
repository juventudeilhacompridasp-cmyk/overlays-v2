import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayMotion, overlayOrigin, overlayPosition, overlayStyle, scoreboardFrame } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';

/** Mais de 10 linhas: espaçamento e escudos menores para a tabela caber no quadro 1920 x 1080. */
const DENSE_AFTER = 10;

export default function Standings({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const table = state.standings;
    const appearance = state.appearance;
    const visible = table !== null;
    const dense = (table?.rows.length ?? 0) > DENSE_AFTER;
    const frame = scoreboardFrame({ ...appearance, layout: 'bar' });
    const cell = dense ? 'py-0.5' : 'py-1.5';
    const delay = (index: number) => (appearance.motion === 'dynamic' ? { animationDelay: `${index * 45}ms` } : undefined);

    return (
        <div className={`fixed inset-0 flex p-10 font-sans transition-opacity duration-500 ${overlayPosition(appearance.position)}`} style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 2px rgba(0,0,0,.55)' }} data-testid="overlay-standings" data-visible={visible}>
            <Head title="Tabela" />
            {table && (
                <div style={{ transform: `scale(${appearance.scale})`, transformOrigin: overlayOrigin(appearance.position) }}>
                    <div className={overlayMotion(appearance.motion)}>
                        <div className={`${frame.className.replace('inline-flex items-stretch', 'block')} w-[36rem]`} style={frame.style}>
                            <div className="flex items-center justify-between px-5 py-2 text-xl font-black uppercase tracking-wide" style={{ backgroundColor: appearance.accent_color }}>
                                <span>{table.title}</span>
                                <span className="text-xs font-bold tracking-[0.2em] opacity-80">CLASSIFICAÇÃO</span>
                            </div>
                            <table className="w-full text-lg">
                                <thead className="text-xs uppercase text-white/50">
                                    <tr>
                                        <th className="w-12 px-3 py-1 text-right">#</th>
                                        <th className="px-2 py-1 text-left">Equipe</th>
                                        <th className="px-2 py-1 text-center">P</th>
                                        <th className="px-2 py-1 text-center">J</th>
                                        <th className="px-2 py-1 text-center">V</th>
                                        <th className="px-2 py-1 text-center">E</th>
                                        <th className="px-2 py-1 text-center">D</th>
                                        <th className="px-3 py-1 text-center">SG</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {table.rows.map((row, index) => (
                                        <tr key={row.team} className={`${row.highlight ? 'font-bold' : ''} ${appearance.motion === 'dynamic' ? 'overlay-row-in' : ''}`} style={{ ...(row.highlight ? { backgroundColor: `${appearance.accent_color}3f` } : { backgroundColor: index % 2 === 1 ? 'rgba(255,255,255,.04)' : undefined }), ...delay(index) }}>
                                            <td className={`relative px-3 text-right tabular-nums text-white/70 ${cell}`}>
                                                <span className="absolute inset-y-0.5 left-0 w-1 rounded-r" style={{ backgroundColor: row.color ?? appearance.accent_color }} />
                                                {row.position}
                                            </td>
                                            <td className={`px-2 ${cell}`}>
                                                <span className="flex items-center gap-2">
                                                    {row.logo_url && <img src={row.logo_url} alt="" className={dense ? 'h-5 w-5 object-contain' : 'h-7 w-7 object-contain'} />}
                                                    <span className="truncate">{row.team}</span>
                                                </span>
                                            </td>
                                            <td className={`px-2 text-center font-black tabular-nums ${cell}`}>{row.points}</td>
                                            <td className={`px-2 text-center tabular-nums ${cell}`}>{row.played}</td>
                                            <td className={`px-2 text-center tabular-nums text-white/80 ${cell}`}>{row.won}</td>
                                            <td className={`px-2 text-center tabular-nums text-white/80 ${cell}`}>{row.drawn}</td>
                                            <td className={`px-2 text-center tabular-nums text-white/80 ${cell}`}>{row.lost}</td>
                                            <td className={`px-3 text-center tabular-nums ${cell}`}>{row.goal_difference > 0 ? `+${row.goal_difference}` : row.goal_difference}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

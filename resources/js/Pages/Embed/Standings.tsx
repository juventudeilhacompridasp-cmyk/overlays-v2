import Frame, { EmbedOptions, line, muted } from '@/Components/Embed/Frame';
import { Head } from '@inertiajs/react';

interface Row {
    position: number;
    team: { name: string; logo_url: string | null; url: string } | null;
    points: number;
    played: number;
    wins: number;
    draws: number;
    losses: number;
    goal_difference: number;
}

export default function Standings({ options, title, tables, portal_url }: { options: EmbedOptions; title: string; tables: { name: string; rows: Row[] }[]; portal_url: string }) {
    return (
        <Frame options={options} footer={{ label: 'Tabela completa', href: portal_url }}>
            <Head title={title} />
            <h1 className="px-3 pt-3 text-base font-bold">{title}</h1>
            {tables.length === 0 && <p className={`px-3 py-4 ${muted(options)}`}>A classificação ainda não está disponível.</p>}
            {tables.map((table) => (
                <div key={table.name} className="overflow-x-auto px-1 pb-2">
                    {tables.length > 1 && <h2 className="px-2 pt-2 text-xs font-semibold uppercase" style={{ color: 'var(--accent)' }}>{table.name}</h2>}
                    <table className="min-w-full text-xs">
                        <thead className={muted(options)}>
                            <tr>
                                <th className="px-2 py-1 text-left">#</th>
                                <th className="px-2 py-1 text-left">Equipe</th>
                                {['P', 'J', 'V', 'E', 'D', 'SG'].map((head) => (
                                    <th key={head} className="px-1.5 py-1 text-center">{head}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className={`divide-y ${line(options)}`}>
                            {table.rows.map((row) => (
                                <tr key={`${table.name}-${row.position}`}>
                                    <td className="px-2 py-1.5 tabular-nums">{row.position}</td>
                                    <td className="px-2 py-1.5 font-medium">
                                        <span className="flex items-center gap-2">
                                            {row.team?.logo_url && <img src={row.team.logo_url} alt="" width={18} height={18} className="h-[18px] w-[18px] object-contain" />}
                                            {row.team?.name ?? '—'}
                                        </span>
                                    </td>
                                    {[row.points, row.played, row.wins, row.draws, row.losses, row.goal_difference].map((value, index) => (
                                        <td key={index} className={`px-1.5 py-1.5 text-center tabular-nums ${index === 0 ? 'font-bold' : ''}`}>{value}</td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ))}
        </Frame>
    );
}

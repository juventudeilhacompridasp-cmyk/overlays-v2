import { GameList, PortalGame } from '@/Components/Portal/GameList';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    position: number;
    team: { id: string; name: string; logo_url: string | null; url: string } | null;
    points: number;
    played: number;
    wins: number;
    draws: number;
    losses: number;
    goals_for: number;
    goals_against: number;
    goal_difference: number;
}

interface Stage {
    id: string;
    name: string;
    type: string;
    knockout: boolean;
    tables: { name: string; rows: Row[] }[];
    rounds: { label: string; games: PortalGame[] }[];
}

interface Division {
    id: string;
    name: string;
    category: string | null;
    stages: Stage[];
    scorers: { name: string; team: string | null; goals: number }[];
}

interface Props {
    theme: Theme;
    seo: Seo;
    competition: { id: string; name: string; slug: string };
    season: { id: string; name: string; year: number; status_label: string; starts_on: string | null; ends_on: string | null };
    divisions: Division[];
    other_seasons: { id: string; name: string; url: string; current: boolean }[];
}

function Table({ table, showCaption }: { table: Stage['tables'][number]; showCaption: boolean }) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                <caption className={showCaption ? 'mb-1 text-left text-sm font-semibold text-gray-700' : 'sr-only'}>{table.name}</caption>
                <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th className="px-2 py-2 text-left">#</th>
                        <th className="px-2 py-2 text-left">Equipe</th>
                        {['P', 'J', 'V', 'E', 'D', 'GP', 'GC', 'SG'].map((head) => (
                            <th key={head} className="px-2 py-2 text-center">{head}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {table.rows.map((row) => (
                        <tr key={row.team?.id ?? row.position}>
                            <td className="px-2 py-2 text-gray-500">{row.position}</td>
                            <td className="px-2 py-2 font-medium">
                                <span className="flex items-center gap-2">
                                    {row.team?.logo_url && <img src={row.team.logo_url} alt="" width={20} height={20} loading="lazy" className="h-5 w-5 object-contain" />}
                                    {row.team ? <Link href={row.team.url} className="hover:underline">{row.team.name}</Link> : '—'}
                                </span>
                            </td>
                            <td className="px-2 py-2 text-center font-bold">{row.points}</td>
                            <td className="px-2 py-2 text-center">{row.played}</td>
                            <td className="px-2 py-2 text-center">{row.wins}</td>
                            <td className="px-2 py-2 text-center">{row.draws}</td>
                            <td className="px-2 py-2 text-center">{row.losses}</td>
                            <td className="px-2 py-2 text-center">{row.goals_for}</td>
                            <td className="px-2 py-2 text-center">{row.goals_against}</td>
                            <td className="px-2 py-2 text-center">{row.goal_difference}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function DivisionView({ division }: { division: Division }) {
    return (
        <div className="space-y-6">
            {division.stages.map((stage) => (
                <section key={stage.id} className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 print:break-inside-avoid-page print:shadow-none">
                    <h3 className="mb-3 text-lg font-bold">{stage.name}</h3>
                    {stage.tables.length > 0 && <div className="mb-5 space-y-4">{stage.tables.map((table) => <Table key={table.name} table={table} showCaption={stage.tables.length > 1 || table.name !== stage.name} />)}</div>}
                    {stage.rounds.length === 0 ? (
                        <p className="text-sm text-gray-500">A tabela de jogos ainda não foi divulgada.</p>
                    ) : (
                        <div className="space-y-4">
                            {stage.rounds.map((round) => (
                                <div key={round.label}>
                                    <h4 className="text-xs font-semibold uppercase tracking-wide text-gray-500">{round.label}</h4>
                                    <GameList games={round.games} />
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            ))}
            {division.scorers.length > 0 && (
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 print:break-inside-avoid-page print:shadow-none">
                    <h3 className="mb-3 text-lg font-bold">Artilharia</h3>
                    <ol className="divide-y divide-gray-100 text-sm">
                        {division.scorers.map((scorer, index) => (
                            <li key={index} className="flex items-center justify-between py-2">
                                <span>
                                    <span className="mr-3 inline-block w-5 text-gray-400">{index + 1}</span>
                                    <span className="font-medium">{scorer.name}</span>
                                    {scorer.team && <span className="ml-2 text-gray-500">{scorer.team}</span>}
                                </span>
                                <span className="font-bold tabular-nums">{scorer.goals}</span>
                            </li>
                        ))}
                    </ol>
                </section>
            )}
        </div>
    );
}

export default function Season({ theme, seo, competition, season, divisions, other_seasons }: Props) {
    const [active, setActive] = useState(divisions[0]?.id);

    return (
        <PortalLayout theme={theme} seo={seo}>
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-extrabold">{competition.name}</h1>
                    <p className="text-gray-600">
                        {season.name} · {season.status_label}
                    </p>
                </div>
                <button type="button" onClick={() => window.print()} className="rounded-md bg-white px-3 py-2 text-sm font-medium ring-1 ring-gray-300 hover:bg-gray-100 print:hidden">
                    Imprimir (A4)
                </button>
            </div>

            {other_seasons.length > 1 && (
                <nav className="mb-4 flex flex-wrap gap-2 print:hidden" aria-label="Edições">
                    {other_seasons.map((other) => (
                        <Link key={other.id} href={other.url} className={`rounded-full px-3 py-1 text-sm ${other.current ? 'text-white' : 'bg-gray-100 hover:bg-gray-200'}`} style={other.current ? { backgroundColor: theme.primary } : undefined}>
                            {other.name}
                        </Link>
                    ))}
                </nav>
            )}

            {divisions.length === 0 ? (
                <p className="text-gray-500">Esta edição ainda não tem divisões publicadas.</p>
            ) : (
                <>
                    <div role="tablist" className="mb-4 flex flex-wrap gap-2 print:hidden">
                        {divisions.map((division) => (
                            <button
                                key={division.id}
                                type="button"
                                role="tab"
                                aria-selected={division.id === active}
                                onClick={() => setActive(division.id)}
                                className={`rounded-md px-4 py-2 text-sm font-medium ${division.id === active ? 'text-white' : 'bg-white ring-1 ring-gray-300 hover:bg-gray-100'}`}
                                style={division.id === active ? { backgroundColor: theme.primary } : undefined}
                            >
                                {division.name}
                            </button>
                        ))}
                    </div>
                    {/* Na impressão saem todas as divisões; na tela, só a aba ativa (todas vão no HTML do servidor, para busca). */}
                    {divisions.map((division) => (
                        <div key={division.id} role="tabpanel" className={`${division.id === active ? 'block' : 'hidden'} print:mb-8 print:block`}>
                            <h2 className="mb-3 hidden text-xl font-bold print:block">{division.name}</h2>
                            <DivisionView division={division} />
                        </div>
                    ))}
                </>
            )}
        </PortalLayout>
    );
}

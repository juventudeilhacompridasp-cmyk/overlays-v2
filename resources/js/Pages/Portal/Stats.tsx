import { GameList, PortalGame } from '@/Components/Portal/GameList';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

interface TeamRef {
    id: string;
    name: string;
    short_name: string | null;
    logo_url: string | null;
    url: string;
}

interface TeamRow {
    team: TeamRef | null;
    played: number;
    wins: number;
    draws: number;
    losses: number;
    for: number;
    against: number;
    clean_sheets: number;
    avg_for: number;
    avg_against: number;
    win_rate: number;
    form: ('V' | 'E' | 'D')[];
}

interface Division {
    id: string;
    name: string;
    summary: { games: number; total: number; average: number; home_wins: number; draws: number; away_wins: number };
    best_attack: TeamRow | null;
    best_defense: TeamRow | null;
    teams: TeamRow[];
    biggest_wins: PortalGame[];
    highest_scoring: PortalGame[];
}

interface Props {
    theme: Theme;
    seo: Seo;
    competition: { name: string; slug: string };
    season: { id: string; name: string; url: string };
    unit: string;
    unit_singular: string;
    divisions: Division[];
}

const formTone = { V: 'bg-emerald-100 text-emerald-800', E: 'bg-gray-200 text-gray-700', D: 'bg-red-100 text-red-800' } as const;

function Highlight({ title, row, value }: { title: string; row: TeamRow | null; value: string }) {
    if (!row || !row.team) return null;

    return (
        <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <div className="text-xs uppercase text-gray-500">{title}</div>
            <Link href={row.team.url} className="mt-1 block text-lg font-bold hover:underline">
                {row.team.name}
            </Link>
            <div className="text-sm text-gray-600">{value}</div>
        </div>
    );
}

export default function Stats({ theme, seo, competition, season, unit, divisions }: Props) {
    const [active, setActive] = useState(divisions[0]?.id);
    const division = divisions.find((item) => item.id === active) ?? divisions[0];

    return (
        <PortalLayout theme={theme} seo={seo}>
            <h1 className="text-2xl font-extrabold">Estatísticas</h1>
            <p className="text-gray-600">
                <Link href={season.url} className="hover:underline">
                    {competition.name} · {season.name}
                </Link>
            </p>

            {divisions.length === 0 || !division ? (
                <p className="mt-6 text-gray-500">Ainda não há divisões nesta edição.</p>
            ) : (
                <>
                    <div role="tablist" className="my-4 flex flex-wrap gap-2">
                        {divisions.map((item) => (
                            <button key={item.id} type="button" role="tab" aria-selected={item.id === division.id} onClick={() => setActive(item.id)} className={`rounded-md px-4 py-2 text-sm font-medium ${item.id === division.id ? 'text-white' : 'bg-white ring-1 ring-gray-300 hover:bg-gray-100'}`} style={item.id === division.id ? { backgroundColor: theme.primary } : undefined}>
                                {item.name}
                            </button>
                        ))}
                    </div>

                    {division.summary.games === 0 ? (
                        <p className="text-gray-500">Nenhum jogo encerrado ainda.</p>
                    ) : (
                        <div className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                                    <div className="text-xs uppercase text-gray-500">Jogos</div>
                                    <div className="text-2xl font-bold" data-testid="stats-games">{division.summary.games}</div>
                                </div>
                                <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                                    <div className="text-xs uppercase text-gray-500">Total de {unit}</div>
                                    <div className="text-2xl font-bold">{division.summary.total}</div>
                                    <div className="text-sm text-gray-600">{division.summary.average} por jogo</div>
                                </div>
                                <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                                    <div className="text-xs uppercase text-gray-500">Mandante × empate × visitante</div>
                                    <div className="text-2xl font-bold">
                                        {division.summary.home_wins} × {division.summary.draws} × {division.summary.away_wins}
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Highlight title="Melhor ataque" row={division.best_attack} value={`${division.best_attack?.avg_for} ${unit} por jogo (${division.best_attack?.for} em ${division.best_attack?.played})`} />
                                <Highlight title="Melhor defesa" row={division.best_defense} value={`${division.best_defense?.avg_against} ${unit} sofridos por jogo · ${division.best_defense?.clean_sheets} sem sofrer`} />
                            </div>

                            <section className="overflow-x-auto rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                                <h2 className="mb-3 text-lg font-bold">Equipes</h2>
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                        <tr>
                                            <th className="px-2 py-2 text-left">Equipe</th>
                                            {['J', 'V', 'E', 'D', 'Pró', 'Contra', 'Méd. pró', 'Méd. contra', 'Aprov.'].map((head) => (
                                                <th key={head} className="px-2 py-2 text-center">{head}</th>
                                            ))}
                                            <th className="px-2 py-2 text-left">Forma</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {division.teams.map((row) => (
                                            <tr key={row.team?.id}>
                                                <td className="px-2 py-2 font-medium">{row.team ? <Link href={row.team.url} className="hover:underline">{row.team.name}</Link> : '—'}</td>
                                                {[row.played, row.wins, row.draws, row.losses, row.for, row.against, row.avg_for, row.avg_against, `${row.win_rate}%`].map((value, index) => (
                                                    <td key={index} className="px-2 py-2 text-center tabular-nums">{value}</td>
                                                ))}
                                                <td className="px-2 py-2">
                                                    <span className="flex gap-1">
                                                        {row.form.map((result, index) => (
                                                            <span key={index} className={`inline-flex h-5 w-5 items-center justify-center rounded text-xs font-bold ${formTone[result]}`}>{result}</span>
                                                        ))}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </section>

                            <div className="grid gap-6 lg:grid-cols-2">
                                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                                    <h2 className="mb-3 text-lg font-bold">Maiores vitórias</h2>
                                    <GameList games={division.biggest_wins} />
                                </section>
                                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                                    <h2 className="mb-3 text-lg font-bold">Jogos com mais {unit}</h2>
                                    <GameList games={division.highest_scoring} />
                                </section>
                            </div>
                        </div>
                    )}
                </>
            )}
        </PortalLayout>
    );
}

import DataTable from '@/Components/DataTable';
import { GameList, PortalGame } from '@/Components/Portal/GameList';
import ShareButtons from '@/Components/Portal/ShareButtons';
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

interface BracketTeam {
    id: string;
    name: string;
    logo_url: string | null;
    url: string;
}

interface BracketSide {
    team: BracketTeam | null;
    score: number | null;
    winner: boolean;
}

interface BracketTie {
    id: string;
    a: BracketSide;
    b: BracketSide;
    decided: boolean;
    live: boolean;
    penalties: { a: number; b: number } | null;
    legs: { url: string; leg: number | null; home: string | null; away: string | null; home_score: number; away_score: number; status_label: string; decided: boolean; scheduled_at: string | null; venue: string | null }[];
}

interface Bracket {
    rounds: { label: string; ties: BracketTie[] }[];
    champion: BracketTeam | null;
}

interface Extras {
    brackets: Record<string, Bracket> | null;
    rules: Record<string, { points: { label: string; value: number }[]; tiebreakers: string[] }> | null;
}

interface Props {
    extras: Extras;
    theme: Theme;
    seo: Seo;
    competition: { id: string; name: string; slug: string };
    season: { id: string; name: string; year: number; status_label: string; starts_on: string | null; ends_on: string | null };
    divisions: Division[];
    stats_url: string | null;
    registration_url: string | null;
    other_seasons: { id: string; name: string; url: string; current: boolean }[];
}

function Table({ table, showCaption }: { table: Stage['tables'][number]; showCaption: boolean }) {
    const numeric: [string, string, (row: Row) => number][] = [
        ['points', 'P', (row) => row.points],
        ['played', 'J', (row) => row.played],
        ['wins', 'V', (row) => row.wins],
        ['draws', 'E', (row) => row.draws],
        ['losses', 'D', (row) => row.losses],
        ['goals_for', 'GP', (row) => row.goals_for],
        ['goals_against', 'GC', (row) => row.goals_against],
        ['goal_difference', 'SG', (row) => row.goal_difference],
    ];

    return (
        <DataTable
            flush
            layout="table"
            paginated={false}
            searchable={false}
            caption={table.name}
            title={showCaption ? table.name : undefined}
            rows={table.rows}
            rowKey={(row) => row.team?.id ?? row.position}
            columns={[
                { key: 'position', header: '#', value: (row) => row.position, cell: (row) => <span className="text-gray-500">{row.position}</span> },
                {
                    key: 'team',
                    header: 'Equipe',
                    sticky: true,
                    value: (row) => row.team?.name,
                    cell: (row) => (
                        <span className="flex items-center gap-2 font-medium">
                            {row.team?.logo_url && <img src={row.team.logo_url} alt="" width={20} height={20} loading="lazy" className="h-5 w-5 object-contain" />}
                            {row.team ? <Link href={row.team.url} className="hover:underline">{row.team.name}</Link> : '—'}
                        </span>
                    ),
                },
                ...numeric.map(([key, header, value]) => ({
                    key,
                    header,
                    align: 'center' as const,
                    value,
                    cell: (row: Row) => <span className={key === 'points' ? 'font-bold' : ''}>{value(row)}</span>,
                })),
            ]}
        />
    );
}

function SideRow({ side, penalties }: { side: BracketSide; penalties: number | null }) {
    return (
        <div className={`flex items-center justify-between gap-2 px-2 py-1.5 ${side.winner ? 'bg-emerald-50 font-bold' : ''}`}>
            <span className="flex min-w-0 items-center gap-1.5">
                {side.team?.logo_url && <img src={side.team.logo_url} alt="" width={16} height={16} loading="lazy" className="h-4 w-4 object-contain" />}
                {side.team ? (
                    <Link href={side.team.url} className="truncate hover:underline">
                        {side.team.name}
                    </Link>
                ) : (
                    <span className="text-gray-400">A definir</span>
                )}
            </span>
            <span className="tabular-nums">
                {side.score ?? '–'}
                {penalties !== null && <span className="ml-1 text-xs font-normal text-gray-500">({penalties})</span>}
            </span>
        </div>
    );
}

function BracketView({ bracket }: { bracket: Bracket }) {
    return (
        <div data-testid="bracket">
            {bracket.champion && (
                <p className="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900" data-testid="champion">
                    🏆 Campeão: {bracket.champion.name}
                </p>
            )}
            <div className="flex gap-6 overflow-x-auto pb-2">
                {bracket.rounds.map((round) => (
                    <div key={round.label} className="flex min-w-56 flex-col justify-around gap-3">
                        <h4 className="text-xs font-semibold uppercase tracking-wide text-gray-500">{round.label}</h4>
                        {round.ties.map((tie) => (
                            <div key={tie.id} className={`overflow-hidden rounded-md text-sm ring-1 ${tie.live ? 'ring-red-400' : 'ring-gray-200'}`}>
                                <SideRow side={tie.a} penalties={tie.penalties?.a ?? null} />
                                <div className="border-t border-gray-100" />
                                <SideRow side={tie.b} penalties={tie.penalties?.b ?? null} />
                                <div className="border-t border-gray-100 bg-gray-50 px-2 py-1 text-xs text-gray-500">
                                    {tie.legs.map((leg) => (
                                        <a key={leg.url} href={leg.url} className="block hover:underline">
                                            {tie.legs.length > 1 ? `Jogo ${leg.leg}: ` : ''}
                                            {leg.decided || tie.live ? `${leg.home ?? '?'} ${leg.home_score} x ${leg.away_score} ${leg.away ?? '?'}` : (leg.scheduled_at ? new Date(leg.scheduled_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'A marcar')}
                                        </a>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

function RulesView({ rules }: { rules: { points: { label: string; value: number }[]; tiebreakers: string[] } }) {
    return (
        <details className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 print:shadow-none" data-testid="rules">
            <summary className="cursor-pointer text-sm font-semibold">Como a classificação é calculada</summary>
            <div className="mt-3 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <h4 className="mb-1 font-medium">Pontos</h4>
                    <ul className="space-y-0.5">
                        {rules.points.map((item) => (
                            <li key={item.label} className="flex justify-between gap-4">
                                <span>{item.label}</span>
                                <span className="font-semibold tabular-nums">{item.value}</span>
                            </li>
                        ))}
                    </ul>
                </div>
                <div>
                    <h4 className="mb-1 font-medium">Critérios de desempate, em ordem</h4>
                    <ol className="list-decimal space-y-0.5 pl-5">
                        {rules.tiebreakers.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ol>
                </div>
            </div>
        </details>
    );
}

function DivisionView({ division, extras }: { division: Division; extras: Extras }) {
    return (
        <div className="space-y-6">
            {division.stages.map((stage) => (
                <section key={stage.id} className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 print:break-inside-avoid-page print:shadow-none">
                    <h3 className="mb-3 text-lg font-bold">{stage.name}</h3>
                    {extras.brackets?.[stage.id] && (
                        <div className="mb-5">
                            <BracketView bracket={extras.brackets[stage.id]} />
                        </div>
                    )}
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
            {extras.rules?.[division.id] && !division.stages.every((stage) => stage.knockout) && <RulesView rules={extras.rules[division.id]} />}
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

export default function Season({ theme, seo, competition, season, divisions, stats_url, registration_url, other_seasons, extras }: Props) {
    const [active, setActive] = useState(divisions[0]?.id);

    return (
        <PortalLayout theme={theme} seo={seo}>
            {registration_url && (
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900 print:hidden">
                    <span className="font-medium">Inscrições abertas para esta edição.</span>
                    <a href={registration_url} className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Inscrever minha equipe
                    </a>
                </div>
            )}
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-extrabold">{competition.name}</h1>
                    <p className="text-gray-600">
                        {season.name} · {season.status_label}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2 print:hidden">
                    <ShareButtons text={`${competition.name} ${season.name} — classificação e jogos:`} url={route('portal.season', [competition.slug, season.id])} />
                    {stats_url && (
                        <Link href={stats_url} className="rounded-md bg-white px-3 py-2 text-sm font-medium ring-1 ring-gray-300 hover:bg-gray-100">
                            Estatísticas
                        </Link>
                    )}
                    <button type="button" onClick={() => window.print()} className="rounded-md bg-white px-3 py-2 text-sm font-medium ring-1 ring-gray-300 hover:bg-gray-100">
                        Imprimir (A4)
                    </button>
                </div>
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
                            <DivisionView division={division} extras={extras} />
                        </div>
                    ))}
                </>
            )}
        </PortalLayout>
    );
}

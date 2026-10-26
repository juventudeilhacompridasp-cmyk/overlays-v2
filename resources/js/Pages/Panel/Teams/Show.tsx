import DataTable from '@/Components/DataTable';
import { Badge, Card, Empty, LinkButton, Logo, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, Link } from '@inertiajs/react';

interface Props {
    team: { id: string; name: string; short_name: string | null; city: string | null; primary_color: string | null; logo_url: string | null };
    entries: { id: string; label: string; competition: string; season: string; division: string; status_label: string; athletes: number }[];
    roster: {
        id: string;
        entry: string;
        role: string;
        shirt_number: number | null;
        status: string;
        status_label: string;
        person: { id: string; name: string; nickname: string | null; age: number | null; photo_url: string | null };
    }[];
    record: { played: number; wins: number; draws: number; losses: number };
    upcoming: GameLine[];
    recent: GameLine[];
}

interface GameLine {
    id: string;
    home: boolean;
    opponent: string | null;
    my_score: number;
    opponent_score: number;
    decided: boolean;
    status_label: string;
    competition: string;
    scheduled_at: string | null;
    venue: string | null;
}

function Games({ games, empty }: { games: GameLine[]; empty: string }) {
    if (games.length === 0) return <p className="text-sm text-gray-500">{empty}</p>;

    return (
        <ul className="divide-y divide-gray-100">
            {games.map((game) => (
                <li key={game.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <div>
                        <div className="font-medium text-gray-900">
                            {game.home ? 'Em casa' : 'Fora'} · {game.decided ? <span className="tabular-nums">{game.my_score} x {game.opponent_score}</span> : 'x'} {game.opponent ?? 'A definir'}
                        </div>
                        <div className="text-xs text-gray-500">
                            {game.competition}
                            {game.scheduled_at ? ` · ${game.scheduled_at}` : ''}
                            {game.venue ? ` · ${game.venue}` : ''}
                        </div>
                    </div>
                    <Badge tone={game.decided ? 'gray' : 'blue'}>{game.status_label}</Badge>
                </li>
            ))}
        </ul>
    );
}

export default function Show({ team, entries, roster, record, upcoming, recent }: Props) {
    const canManage = useCan('manage-registry');
    const stats = [
        { label: 'Jogos', value: record.played },
        { label: 'Vitórias', value: record.wins },
        { label: 'Empates', value: record.draws },
        { label: 'Derrotas', value: record.losses },
    ];

    return (
        <PanelLayout>
            <Head title={team.name} />
            <PageHeader
                title={team.name}
                subtitle={[team.short_name, team.city].filter(Boolean).join(' · ')}
                breadcrumbs={[{ label: 'Equipes', href: route('panel.teams.index') }, { label: team.name }]}
                actions={
                    canManage ? (
                        <LinkButton href={route('panel.teams.edit', team.id)} variant="secondary">
                            Editar equipe
                        </LinkButton>
                    ) : undefined
                }
            />

            <div className="space-y-6">
                <div className="grid gap-4 lg:grid-cols-[18rem_1fr]">
                    <Card>
                        <div className="flex flex-col items-center gap-3 text-center">
                            <Logo src={team.logo_url} name={team.name} size={112} color={team.primary_color} />
                            <div>
                                <div className="text-lg font-semibold text-gray-900">{team.name}</div>
                                {team.short_name && <div className="text-sm text-gray-500">{team.short_name}</div>}
                            </div>
                        </div>
                        <dl className="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-gray-500">Cidade</dt>
                                <dd className="font-medium text-gray-900">{team.city ?? '—'}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-gray-500">Cor principal</dt>
                                <dd className="flex items-center gap-2 font-medium text-gray-900">
                                    {team.primary_color ? (
                                        <>
                                            <span className="h-4 w-4 rounded-full ring-1 ring-gray-300" style={{ backgroundColor: team.primary_color }} aria-hidden />
                                            {team.primary_color}
                                        </>
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-gray-500">Inscrições</dt>
                                <dd className="font-medium text-gray-900">{entries.length}</dd>
                            </div>
                        </dl>
                    </Card>

                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4" data-testid="team-record">
                            {stats.map((stat) => (
                                <Card key={stat.label}>
                                    <p className="text-2xl font-semibold tabular-nums text-gray-900">{stat.value}</p>
                                    <p className="text-sm text-gray-500">{stat.label}</p>
                                </Card>
                            ))}
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Card title="Próximos jogos">
                                <Games games={upcoming} empty="Nenhum jogo a disputar." />
                            </Card>
                            <Card title="Últimos resultados">
                                <Games games={recent} empty="Nenhum jogo encerrado ainda." />
                            </Card>
                        </div>
                    </div>
                </div>

                <DataTable
                    title="Inscrições"
                    caption={`Inscrições de ${team.name}`}
                    searchPlaceholder="Buscar competição, edição ou divisão"
                    rows={entries}
                    rowKey={(entry) => entry.id}
                    empty={<Empty title="Esta equipe ainda não está inscrita em nenhuma competição." />}
                    columns={[
                        { key: 'competition', header: 'Competição', primary: true, filter: true, value: (entry) => entry.competition, cell: (entry) => <span className="font-medium text-gray-900">{entry.competition}</span> },
                        { key: 'season', header: 'Edição', value: (entry) => entry.season, cell: (entry) => <span className="text-gray-600">{entry.season}</span> },
                        { key: 'division', header: 'Divisão', value: (entry) => entry.division, cell: (entry) => <span className="text-gray-600">{entry.division}</span> },
                        { key: 'status', header: 'Situação', filter: true, value: (entry) => entry.status_label, cell: (entry) => <Badge tone={entry.status_label === 'Confirmada' ? 'green' : 'gray'}>{entry.status_label}</Badge> },
                        { key: 'athletes', header: 'Atletas ativos', value: (entry) => entry.athletes, cell: (entry) => <span className="tabular-nums text-gray-600">{entry.athletes}</span> },
                        {
                            key: 'actions',
                            header: 'Ações',
                            align: 'right',
                            mobileLabel: false,
                            cell: (entry) => (
                                <LinkButton href={route('panel.entries.show', entry.id)} variant="secondary" small>
                                    Abrir elenco
                                </LinkButton>
                            ),
                        },
                    ]}
                />

                <div data-testid="team-roster">
                    <DataTable
                        title="Elenco e comissão"
                        caption={`Elenco de ${team.name}`}
                        searchPlaceholder="Buscar por nome, apelido ou camisa"
                        rows={roster}
                        rowKey={(item) => item.id}
                        pageSize={15}
                        persistKey="panel:team-roster"
                        empty={<Empty title="Ninguém no elenco ainda.">Inclua atletas e comissão pela inscrição da equipe em uma divisão.</Empty>}
                        columns={[
                            {
                                key: 'person',
                                header: 'Nome',
                                primary: true,
                                value: (item) => `${item.person.name} ${item.person.nickname ?? ''}`,
                                cell: (item) => (
                                    <span className="flex items-center gap-3">
                                        <Logo src={item.person.photo_url} name={item.person.name} size={32} round />
                                        <span>
                                            <span className="block font-medium text-gray-900">{item.person.name}</span>
                                            {item.person.nickname && <span className="block text-xs text-gray-500">{item.person.nickname}</span>}
                                        </span>
                                    </span>
                                ),
                            },
                            { key: 'shirt', header: 'Camisa', align: 'center', value: (item) => item.shirt_number, cell: (item) => <span className="tabular-nums text-gray-700">{item.shirt_number ?? '—'}</span> },
                            { key: 'role', header: 'Função', filter: true, value: (item) => item.role, cell: (item) => <span className="text-gray-600">{item.role}</span> },
                            { key: 'age', header: 'Idade', align: 'center', hideBelow: 'lg', value: (item) => item.person.age, cell: (item) => <span className="tabular-nums text-gray-600">{item.person.age ?? '—'}</span> },
                            { key: 'entry', header: 'Edição', filter: true, hideBelow: 'lg', value: (item) => item.entry, cell: (item) => <span className="text-xs text-gray-500">{item.entry}</span> },
                            { key: 'status', header: 'Situação', filter: true, value: (item) => item.status_label, cell: (item) => <Badge tone={item.status === 'active' ? 'green' : 'gray'}>{item.status_label}</Badge> },
                            {
                                key: 'actions',
                                header: 'Ações',
                                align: 'right',
                                mobileLabel: false,
                                cell: (item) =>
                                    canManage ? (
                                        <Link href={route('panel.people.edit', item.person.id)} className="text-sm text-emerald-700 underline">
                                            Editar pessoa
                                        </Link>
                                    ) : null,
                            },
                        ]}
                    />
                </div>
            </div>
        </PanelLayout>
    );
}

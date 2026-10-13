import { Badge, Card, Empty, LinkButton, Logo, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link } from '@inertiajs/react';
import { GameRow } from './Games/GameRow';

interface Props {
    teams: { id: string; name: string; logo_url: string | null }[];
    entries: { id: string; team: string; competition: string; season: string; division: string; status: string }[];
    upcoming: GameRow[];
    recent: GameRow[];
}

export default function Dashboard({ teams, entries, upcoming, recent }: Props) {
    return (
        <PanelLayout>
            <Head title="Área do gestor" />
            <PageHeader title="Área do gestor" subtitle="Defina a escalação e o esquema tático dos seus jogos e acompanhe o elenco." />

            {teams.length === 0 ? (
                <Empty title="Você ainda não tem equipes vinculadas">Peça ao organizador da competição para vincular a sua equipe ao seu acesso.</Empty>
            ) : (
                <div className="space-y-6">
                    <div className="flex flex-wrap gap-3">
                        {teams.map((team) => (
                            <div key={team.id} className="flex items-center gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
                                <Logo src={team.logo_url} name={team.name} size={36} />
                                <span className="font-semibold text-gray-900">{team.name}</span>
                            </div>
                        ))}
                    </div>

                    <Card title="Próximos jogos" actions={<LinkButton small variant="secondary" href={route('manager.games.index')}>Ver todos</LinkButton>}>
                        {upcoming.length === 0 ? (
                            <p className="text-sm text-gray-500">Nenhum jogo agendado ainda.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {upcoming.map((game) => (
                                    <li key={game.id}>
                                        <GameLine game={game} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card title="Elencos por edição">
                        {entries.length === 0 ? (
                            <p className="text-sm text-gray-500">Suas equipes ainda não estão inscritas em nenhuma edição.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {entries.map((entry) => (
                                    <li key={entry.id} className="flex flex-wrap items-center justify-between gap-2 py-3">
                                        <div>
                                            <div className="font-medium text-gray-900">{entry.team}</div>
                                            <div className="text-sm text-gray-500">
                                                {entry.competition} · {entry.season} · {entry.division}
                                            </div>
                                        </div>
                                        <LinkButton small variant="secondary" href={route('manager.entries.show', entry.id)}>
                                            Elenco e disciplina
                                        </LinkButton>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    {recent.length > 0 && (
                        <Card title="Últimos resultados">
                            <ul className="divide-y divide-gray-100">
                                {recent.map((game) => (
                                    <li key={game.id}>
                                        <GameLine game={game} />
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>
            )}
        </PanelLayout>
    );
}

export function GameLine({ game }: { game: GameRow }) {
    return (
        <Link href={route('manager.games.show', game.id)} className="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-gray-50">
            <div className="flex items-center gap-3">
                <Logo src={game.my_logo} name={game.my_team} size={28} />
                <div>
                    <div className="font-medium text-gray-900">
                        {game.my_team} {game.decided && <span className="tabular-nums">{game.my_score} x {game.opponent_score}</span>}
                        {!game.decided && 'x'} {game.opponent ?? 'A definir'}
                    </div>
                    <div className="text-xs text-gray-500">
                        {game.competition} · {game.stage}
                        {game.scheduled_at ? ` · ${game.scheduled_at}` : ''}
                        {game.venue ? ` · ${game.venue}` : ''}
                    </div>
                </div>
            </div>
            <div className="flex items-center gap-2">
                {game.lineup && !game.decided && (
                    <Badge tone={game.lineup === 'confirmed' ? 'green' : game.lineup === 'draft' ? 'amber' : 'gray'}>
                        {game.lineup === 'confirmed' ? 'Escalação confirmada' : game.lineup === 'draft' ? 'Rascunho' : 'Sem escalação'}
                    </Badge>
                )}
                <Badge tone={game.status === 'in_progress' ? 'red' : game.decided ? 'gray' : 'blue'}>{game.status_label}</Badge>
            </div>
        </Link>
    );
}

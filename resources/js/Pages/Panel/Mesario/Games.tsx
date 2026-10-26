import DataTable, { serverTable } from '@/Components/DataTable';
import { Badge, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Paginated, TableFilters } from '@/types/panel';
import { Head } from '@inertiajs/react';

interface Game {
    id: string;
    home: string;
    away: string;
    home_score: number;
    away_score: number;
    status: string;
    status_label: string;
    live: boolean;
    open: boolean;
    scheduled_at: string | null;
    venue: string | null;
    competition: string;
    stage: string;
    sport: { label: string };
}

interface Option {
    value: string;
    label: string;
}

export default function Games({ games, filters, statuses, competitions }: { games: Paginated<Game>; filters: TableFilters; statuses: Option[]; competitions: Option[] }) {
    return (
        <PanelLayout>
            <Head title="Jogos do campeonato" />
            <PageHeader
                title="Jogos do campeonato"
                subtitle="Todos os jogos da organização. Abra o painel do operador para acompanhar e gerenciar a partida."
                actions={
                    <LinkButton href={route('panel.mesario')} variant="secondary">
                        Voltar ao mesário
                    </LinkButton>
                }
            />

            <DataTable
                caption="Jogos do campeonato"
                searchPlaceholder="Buscar por equipe"
                rows={games.data}
                rowKey={(game) => game.id}
                server={serverTable(route('panel.mesario.games'), games, filters, { sort: 'when', dir: 'desc', per_page: 25 }, ['games', 'filters'])}
                empty="Nenhum jogo encontrado."
                columns={[
                    {
                        key: 'when',
                        header: 'Quando',
                        nowrap: true,
                        value: (game) => game.scheduled_at,
                        cell: (game) => <span className="text-gray-600">{game.scheduled_at ? new Date(game.scheduled_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'Sem horário'}</span>,
                    },
                    {
                        key: 'game',
                        header: 'Jogo',
                        primary: true,
                        sortable: false,
                        value: (game) => `${game.home} ${game.away}`,
                        cell: (game) => (
                            <div>
                                <div className="font-medium text-gray-900">
                                    {game.home} <span className="font-bold tabular-nums">{game.live || !game.open ? `${game.home_score} x ${game.away_score}` : 'x'}</span> {game.away}
                                </div>
                                {game.venue && <div className="text-xs text-gray-500">{game.venue}</div>}
                            </div>
                        ),
                    },
                    {
                        key: 'competition',
                        header: 'Competição',
                        sortable: false,
                        hideBelow: 'lg',
                        value: (game) => game.competition,
                        filter: competitions,
                        cell: (game) => (
                            <span className="text-gray-700">
                                {game.competition} · {game.stage}
                                <span className="ml-2 text-emerald-700">· {game.sport.label}</span>
                            </span>
                        ),
                    },
                    {
                        key: 'status',
                        header: 'Situação',
                        value: (game) => game.status_label,
                        filter: statuses,
                        cell: (game) => <Badge tone={game.live ? 'red' : game.open ? 'amber' : 'green'}>{game.live ? 'Ao vivo' : game.status_label}</Badge>,
                    },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (game) => (
                            <LinkButton href={route('panel.games.operator', game.id)} small>
                                Painel do operador
                            </LinkButton>
                        ),
                    },
                ]}
            />
        </PanelLayout>
    );
}

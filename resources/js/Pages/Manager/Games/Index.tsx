import DataTable from '@/Components/DataTable';
import { Badge, Empty, LinkButton, Logo, Pagination, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { PaginationLink } from '@/types/panel';
import { Head, Link } from '@inertiajs/react';
import { GameRow } from './GameRow';

interface Props {
    games: { data: GameRow[]; links: PaginationLink[] };
    filter: 'open' | 'done' | 'all';
}

const filters: { value: Props['filter']; label: string }[] = [
    { value: 'open', label: 'Em aberto' },
    { value: 'done', label: 'Encerrados' },
    { value: 'all', label: 'Todos' },
];

export default function Index({ games, filter }: Props) {
    return (
        <PanelLayout>
            <Head title="Jogos" />
            <PageHeader title="Jogos e escalações" subtitle="Escolha um jogo para definir a escalação e o esquema tático." />

            <div className="mb-4 flex gap-2">
                {filters.map((item) => (
                    <Link
                        key={item.value}
                        href={route('manager.games.index', { filtro: item.value })}
                        className={`rounded-full px-3 py-1 text-sm font-medium ${filter === item.value ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'}`}
                    >
                        {item.label}
                    </Link>
                ))}
            </div>

            <DataTable
                caption="Jogos da sua equipe"
                searchPlaceholder="Buscar por equipe, adversário, competição ou local"
                rows={games.data}
                rowKey={(game) => game.id}
                pageSize={15}
                empty={<Empty title="Nenhum jogo por aqui" />}
                columns={[
                    {
                        key: 'game',
                        header: 'Jogo',
                        primary: true,
                        value: (game) => `${game.my_team} ${game.opponent ?? ''}`,
                        cell: (game) => (
                            <Link href={route('manager.games.show', game.id)} className="flex items-center gap-3 hover:underline">
                                <Logo src={game.my_logo} name={game.my_team} size={28} />
                                <span className="font-medium text-gray-900">
                                    {game.my_team} {game.decided ? <span className="tabular-nums">{game.my_score} x {game.opponent_score}</span> : 'x'} {game.opponent ?? 'A definir'}
                                </span>
                            </Link>
                        ),
                    },
                    { key: 'when', header: 'Data', value: (game) => (game.scheduled_at ? game.scheduled_at.split(' ').map((part, index) => (index === 0 ? part.split('/').reverse().join('-') : part)).join(' ') : null), cell: (game) => <span className="text-gray-600">{game.scheduled_at ?? 'Sem data'}</span> },
                    { key: 'competition', header: 'Competição', hideBelow: 'lg', value: (game) => `${game.competition} ${game.stage}`, cell: (game) => <span className="text-gray-600">{game.competition} · {game.stage}</span> },
                    { key: 'venue', header: 'Local', hideBelow: 'xl', value: (game) => game.venue, cell: (game) => <span className="text-gray-600">{game.venue ?? '—'}</span> },
                    {
                        key: 'lineup',
                        header: 'Escalação',
                        filter: [
                            { value: 'Confirmada', label: 'Confirmada' },
                            { value: 'Rascunho', label: 'Rascunho' },
                            { value: 'Sem escalação', label: 'Sem escalação' },
                        ],
                        value: (game) => (game.decided ? null : game.lineup === 'confirmed' ? 'Confirmada' : game.lineup === 'draft' ? 'Rascunho' : 'Sem escalação'),
                        cell: (game) =>
                            game.lineup && !game.decided ? (
                                <Badge tone={game.lineup === 'confirmed' ? 'green' : game.lineup === 'draft' ? 'amber' : 'gray'}>
                                    {game.lineup === 'confirmed' ? 'Confirmada' : game.lineup === 'draft' ? 'Rascunho' : 'Sem escalação'}
                                </Badge>
                            ) : (
                                <span className="text-gray-400">—</span>
                            ),
                    },
                    { key: 'status', header: 'Situação', filter: true, value: (game) => game.status_label, cell: (game) => <Badge tone={game.status === 'in_progress' ? 'red' : game.decided ? 'gray' : 'blue'}>{game.status_label}</Badge> },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (game) => (
                            <LinkButton small variant="secondary" href={route('manager.games.show', game.id)}>
                                Abrir
                            </LinkButton>
                        ),
                    },
                ]}
            />
            <Pagination links={games.links} />
        </PanelLayout>
    );
}

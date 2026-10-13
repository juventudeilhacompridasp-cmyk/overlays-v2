import { Empty, Pagination, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { PaginationLink } from '@/types/panel';
import { Head, Link } from '@inertiajs/react';
import { GameLine } from '../Dashboard';
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

            {games.data.length === 0 ? (
                <Empty title="Nenhum jogo por aqui" />
            ) : (
                <div className="rounded-lg bg-white px-5 shadow-sm ring-1 ring-gray-200">
                    <ul className="divide-y divide-gray-100">
                        {games.data.map((game) => (
                            <li key={game.id}>
                                <GameLine game={game} />
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            <Pagination links={games.links} />
        </PanelLayout>
    );
}

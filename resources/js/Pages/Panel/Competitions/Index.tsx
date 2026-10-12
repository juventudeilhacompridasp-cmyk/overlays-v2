import { Card, DeleteButton, Empty, LinkButton, PageHeader, Pagination, SearchBox } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Paginated } from '@/types/panel';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Competition {
    id: string;
    name: string;
    description: string | null;
    seasons_count: number;
}

export default function Index({ competitions, filters }: { competitions: Paginated<Competition>; filters: { q: string } }) {
    const [search, setSearch] = useState(filters.q);
    const canManage = useCan('manage-competitions');

    return (
        <PanelLayout>
            <Head title="Competições" />
            <PageHeader
                title="Competições"
                subtitle="Campeonatos e copas. Cada competição tem edições (por ano), e cada edição tem divisões."
                actions={canManage ? <LinkButton href={route('panel.competitions.create')}>Nova competição</LinkButton> : undefined}
            />

            <div className="mb-4">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => router.get(route('panel.competitions.index'), { q: search }, { preserveState: true })}
                    placeholder="Buscar competição pelo nome"
                />
            </div>

            {competitions.data.length === 0 ? (
                <Empty title={filters.q ? 'Nenhuma competição encontrada.' : 'Nenhuma competição criada ainda.'}>
                    {!filters.q && 'Crie a primeira competição para começar.'}
                </Empty>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {competitions.data.map((competition) => (
                        <Card key={competition.id}>
                            <Link href={route('panel.competitions.show', competition.id)} className="text-lg font-semibold text-gray-900 hover:text-emerald-700">
                                {competition.name}
                            </Link>
                            <p className="mt-1 line-clamp-2 text-sm text-gray-600">{competition.description ?? 'Sem descrição.'}</p>
                            <div className="mt-4 flex items-center justify-between">
                                <span className="text-sm text-gray-500">
                                    {competition.seasons_count} {competition.seasons_count === 1 ? 'edição' : 'edições'}
                                </span>
                                <div className="flex gap-2">
                                    <LinkButton href={route('panel.competitions.show', competition.id)} variant="secondary" small>
                                        Abrir
                                    </LinkButton>
                                    {canManage && (
                                        <DeleteButton
                                            href={route('panel.competitions.destroy', competition.id)}
                                            message={`Excluir "${competition.name}" com todas as edições, divisões, fases e jogos? Esta ação não pode ser desfeita.`}
                                        />
                                    )}
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}
            <Pagination links={competitions.links} />
        </PanelLayout>
    );
}

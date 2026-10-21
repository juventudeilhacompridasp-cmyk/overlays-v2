import DataTable, { serverTable } from '@/Components/DataTable';
import { DeleteButton, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Paginated, TableFilters } from '@/types/panel';
import { Head, Link } from '@inertiajs/react';

interface Competition {
    id: string;
    name: string;
    description: string | null;
    seasons_count: number;
}

export default function Index({ competitions, filters }: { competitions: Paginated<Competition>; filters: TableFilters }) {
    const canManage = useCan('manage-competitions');

    return (
        <PanelLayout>
            <Head title="Competições" />
            <PageHeader
                title="Competições"
                subtitle="Campeonatos e copas. Cada competição tem edições (por ano), e cada edição tem divisões."
                actions={canManage ? <LinkButton href={route('panel.competitions.create')}>Nova competição</LinkButton> : undefined}
            />

            <DataTable
                caption="Competições"
                searchPlaceholder="Buscar por nome ou descrição"
                rows={competitions.data}
                rowKey={(competition) => competition.id}
                server={serverTable(route('panel.competitions.index'), competitions, filters, { sort: 'name' }, ['competitions', 'filters'])}
                empty="Nenhuma competição criada ainda. Crie a primeira competição para começar."
                columns={[
                    {
                        key: 'name',
                        header: 'Competição',
                        primary: true,
                        value: (competition) => competition.name,
                        cell: (competition) => (
                            <Link href={route('panel.competitions.show', competition.id)} className="font-semibold text-gray-900 hover:text-emerald-700">
                                {competition.name}
                            </Link>
                        ),
                    },
                    {
                        key: 'description',
                        header: 'Descrição',
                        sortable: false,
                        value: (competition) => competition.description,
                        hideBelow: 'lg',
                        cell: (competition) => <span className="line-clamp-2 text-gray-600">{competition.description ?? 'Sem descrição.'}</span>,
                    },
                    {
                        key: 'seasons',
                        header: 'Edições',
                        value: (competition) => competition.seasons_count,
                        filter: [
                            { value: 'with', label: 'Com edições' },
                            { value: 'without', label: 'Sem edições' },
                        ],
                        cell: (competition) => <span className="text-gray-600">{competition.seasons_count}</span>,
                    },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (competition) => (
                            <div className="flex flex-wrap justify-end gap-2">
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
                        ),
                    },
                ]}
            />
        </PanelLayout>
    );
}

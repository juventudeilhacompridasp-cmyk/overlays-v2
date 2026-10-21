import DataTable, { serverTable } from '@/Components/DataTable';
import { DeleteButton, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Paginated, TableFilters } from '@/types/panel';
import { Head, Link } from '@inertiajs/react';

interface Venue {
    id: string;
    name: string;
    city: string | null;
    address: string | null;
    games_count: number;
}

export default function Index({ venues, filters, city_options }: { venues: Paginated<Venue>; filters: TableFilters; city_options: string[] }) {
    const canManage = useCan('manage-registry');

    return (
        <PanelLayout>
            <Head title="Estádios" />
            <PageHeader
                title="Estádios"
                subtitle="Locais onde os jogos acontecem."
                actions={canManage ? <LinkButton href={route('panel.venues.create')}>Novo estádio</LinkButton> : undefined}
            />

            <DataTable
                caption="Estádios"
                searchPlaceholder="Buscar por nome, cidade ou endereço"
                rows={venues.data}
                rowKey={(venue) => venue.id}
                server={serverTable(route('panel.venues.index'), venues, filters, { sort: 'name' }, ['venues', 'filters'])}
                empty="Nenhum estádio cadastrado ainda."
                columns={[
                    {
                        key: 'name',
                        header: 'Estádio',
                        primary: true,
                        value: (venue) => venue.name,
                        cell: (venue) => (
                            <Link href={route('panel.venues.edit', venue.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                {venue.name}
                            </Link>
                        ),
                    },
                    {
                        key: 'city',
                        header: 'Cidade',
                        value: (venue) => venue.city,
                        filter: city_options.map((city) => ({ value: city, label: city })),
                        cell: (venue) => <span className="text-gray-600">{venue.city ?? '—'}</span>,
                    },
                    {
                        key: 'address',
                        header: 'Endereço',
                        value: (venue) => venue.address,
                        hideBelow: 'lg',
                        cell: (venue) => <span className="text-gray-600">{venue.address ?? '—'}</span>,
                    },
                    {
                        key: 'games',
                        header: 'Jogos',
                        value: (venue) => venue.games_count,
                        filter: [
                            { value: 'with', label: 'Com jogos' },
                            { value: 'without', label: 'Sem jogos' },
                        ],
                        cell: (venue) => <span className="text-gray-600">{venue.games_count}</span>,
                    },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (venue) =>
                            canManage ? (
                                <div className="flex flex-wrap justify-end gap-2">
                                    <LinkButton href={route('panel.venues.edit', venue.id)} variant="secondary" small>
                                        Editar
                                    </LinkButton>
                                    <DeleteButton href={route('panel.venues.destroy', venue.id)} message={`Excluir o estádio "${venue.name}"? Os jogos dele ficam sem local.`} />
                                </div>
                            ) : null,
                    },
                ]}
            />
        </PanelLayout>
    );
}

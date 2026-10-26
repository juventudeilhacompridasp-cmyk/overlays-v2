import DataTable, { serverTable } from '@/Components/DataTable';
import { Badge, DeleteButton, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Paginated, TableFilters } from '@/types/panel';
import { Head, Link } from '@inertiajs/react';

interface Official {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    active: boolean;
    assignments_count: number;
}

export default function Index({ officials, filters }: { officials: Paginated<Official>; filters: TableFilters }) {
    return (
        <PanelLayout>
            <Head title="Árbitros e mesários" />
            <PageHeader
                title="Árbitros e mesários"
                subtitle="Quem pode ser escalado nos jogos. A escala de cada jogo é feita na fase da competição, em “Escala de árbitros”."
                actions={<LinkButton href={route('panel.officials.create')}>Novo cadastro</LinkButton>}
            />

            <DataTable
                caption="Árbitros e mesários"
                searchPlaceholder="Buscar por nome, e-mail ou telefone"
                rows={officials.data}
                rowKey={(official) => official.id}
                server={serverTable(route('panel.officials.index'), officials, filters, { sort: 'name' }, ['officials', 'filters'])}
                empty="Nenhum árbitro ou mesário cadastrado ainda."
                columns={[
                    {
                        key: 'name',
                        header: 'Nome',
                        primary: true,
                        value: (official) => official.name,
                        cell: (official) => (
                            <Link href={route('panel.officials.edit', official.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                {official.name}
                            </Link>
                        ),
                    },
                    {
                        key: 'email',
                        header: 'Contato',
                        value: (official) => official.email,
                        hideBelow: 'lg',
                        cell: (official) => (
                            <div className="text-sm text-gray-600">
                                <div>{official.email ?? <span className="text-amber-700">sem e-mail (não recebe aviso)</span>}</div>
                                {official.phone && <div className="text-xs text-gray-500">{official.phone}</div>}
                            </div>
                        ),
                    },
                    {
                        key: 'active',
                        header: 'Situação',
                        value: (official) => (official.active ? 'Ativo' : 'Inativo'),
                        filter: [
                            { value: 'yes', label: 'Ativos' },
                            { value: 'no', label: 'Inativos' },
                        ],
                        cell: (official) => <Badge tone={official.active ? 'green' : 'gray'}>{official.active ? 'Ativo' : 'Inativo'}</Badge>,
                    },
                    {
                        key: 'games',
                        header: 'Jogos escalados',
                        value: (official) => official.assignments_count,
                        cell: (official) => <span className="text-gray-600">{official.assignments_count}</span>,
                    },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (official) => (
                            <div className="flex flex-wrap justify-end gap-2">
                                <LinkButton href={route('panel.officials.edit', official.id)} variant="secondary" small>
                                    Editar
                                </LinkButton>
                                <DeleteButton href={route('panel.officials.destroy', official.id)} message={`Excluir "${official.name}"? Ele sai da escala de todos os jogos.`} />
                            </div>
                        ),
                    },
                ]}
            />
        </PanelLayout>
    );
}

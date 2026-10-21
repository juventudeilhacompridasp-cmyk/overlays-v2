import DataTable, { serverTable } from '@/Components/DataTable';
import { Badge, Empty, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Paginated, TableFilters } from '@/types/panel';
import { Head, Link } from '@inertiajs/react';

interface Row {
    id: string;
    team_name: string;
    supplement: boolean;
    competition: string;
    season: string;
    division: string;
    status: string;
    status_label: string;
    athletes_count: number;
    people_count: number;
    submitted_at: string | null;
    created_at: string | null;
}

interface Props {
    registrations: Paginated<Row>;
    filters: TableFilters;
    statuses: { value: string; label: string }[];
    pending: number;
}

const tone = (status: string) => (status === 'submitted' ? 'amber' : status === 'approved' ? 'green' : status === 'rejected' ? 'red' : status === 'returned' ? 'blue' : 'gray');

export default function Index({ registrations, filters, statuses, pending }: Props) {
    return (
        <PanelLayout>
            <Head title="Inscrições" />
            <PageHeader title="Inscrições" subtitle={pending > 0 ? `${pending} pedido(s) aguardando análise` : 'Nenhum pedido aguardando análise'} />

            <DataTable
                caption="Pedidos de inscrição"
                searchPlaceholder="Buscar por equipe, competição, edição ou divisão"
                rows={registrations.data}
                rowKey={(row) => row.id}
                server={serverTable(route('panel.registrations.index'), registrations, filters, { sort: 'priority', per_page: 15 }, ['registrations', 'filters', 'pending'])}
                empty={<Empty title="Nenhum pedido de inscrição.">Abra as inscrições em uma edição (status “Inscrições abertas”) e divulgue o link público.</Empty>}
                columns={[
                    {
                        key: 'team',
                        header: 'Equipe',
                        primary: true,
                        value: (row) => row.team_name,
                        cell: (row) => (
                            <Link href={route('panel.registrations.show', row.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                {row.team_name}
                                {row.supplement && <span className="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Reforço</span>}
                            </Link>
                        ),
                    },
                    {
                        key: 'season',
                        header: 'Edição',
                        sortable: false,
                        value: (row) => `${row.competition} ${row.season} ${row.division}`,
                        hideBelow: 'lg',
                        cell: (row) => (
                            <span className="text-gray-600">
                                {row.competition} · {row.season} · {row.division}
                            </span>
                        ),
                    },
                    {
                        key: 'status',
                        header: 'Situação',
                        sortKey: 'priority',
                        value: (row) => row.status_label,
                        filter: statuses,
                        cell: (row) => <Badge tone={tone(row.status)}>{row.status_label}</Badge>,
                    },
                    {
                        key: 'athletes',
                        header: 'Atletas',
                        value: (row) => row.athletes_count,
                        cell: (row) => (
                            <span className="text-gray-600">
                                {row.athletes_count} ({row.people_count} pessoas)
                            </span>
                        ),
                    },
                    {
                        key: 'sent',
                        header: 'Enviado em',
                        value: (row) => row.submitted_at ?? row.created_at,
                        cell: (row) => <span className="text-gray-600">{row.submitted_at ?? `criado em ${row.created_at}`}</span>,
                    },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (row) => (
                            <LinkButton href={route('panel.registrations.show', row.id)} variant="secondary" small>
                                Abrir
                            </LinkButton>
                        ),
                    },
                ]}
            />
        </PanelLayout>
    );
}

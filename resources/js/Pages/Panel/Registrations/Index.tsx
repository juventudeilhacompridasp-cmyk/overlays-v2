import { Badge, Card, Empty, LinkButton, PageHeader, Pagination, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { PaginationLink } from '@/types/panel';
import { Head, Link, router } from '@inertiajs/react';

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
    registrations: { data: Row[]; links: PaginationLink[] };
    filters: { status: string };
    statuses: { value: string; label: string }[];
    pending: number;
}

const tone = (status: string) => (status === 'submitted' ? 'amber' : status === 'approved' ? 'green' : status === 'rejected' ? 'red' : status === 'returned' ? 'blue' : 'gray');

export default function Index({ registrations, filters, statuses, pending }: Props) {
    return (
        <PanelLayout>
            <Head title="Inscrições" />
            <PageHeader title="Inscrições" subtitle={pending > 0 ? `${pending} pedido(s) aguardando análise` : 'Nenhum pedido aguardando análise'} />
            <div className="mb-4 w-60">
                <Select value={filters.status} onChange={(e) => router.get(route('panel.registrations.index'), e.target.value ? { status: e.target.value } : {}, { preserveState: true })}>
                    <option value="">Todos os status</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </Select>
            </div>
            {registrations.data.length === 0 ? (
                <Empty title="Nenhum pedido de inscrição.">Abra as inscrições em uma edição (status “Inscrições abertas”) e divulgue o link público.</Empty>
            ) : (
                <Card>
                    <ul className="-my-3 divide-y divide-gray-100">
                        {registrations.data.map((row) => (
                            <li key={row.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div>
                                    <Link href={route('panel.registrations.show', row.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                        {row.team_name}{row.supplement && <span className="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Reforço</span>}
                                    </Link>{' '}
                                    <Badge tone={tone(row.status)}>{row.status_label}</Badge>
                                    <div className="mt-0.5 text-sm text-gray-500">
                                        {row.competition} · {row.season} · {row.division} · {row.athletes_count} atletas ({row.people_count} pessoas) · {row.submitted_at ? `enviado em ${row.submitted_at}` : `criado em ${row.created_at}`}
                                    </div>
                                </div>
                                <LinkButton href={route('panel.registrations.show', row.id)} variant="secondary" small>
                                    Abrir
                                </LinkButton>
                            </li>
                        ))}
                    </ul>
                    <Pagination links={registrations.links} />
                </Card>
            )}
        </PanelLayout>
    );
}

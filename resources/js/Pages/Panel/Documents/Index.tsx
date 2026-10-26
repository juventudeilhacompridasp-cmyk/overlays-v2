import DataTable from '@/Components/DataTable';
import { Badge, PageHeader, StatCard } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link } from '@inertiajs/react';

interface Row {
    id: string;
    person_id: string;
    person: string;
    type_label: string;
    expires_on: string;
    days_left: number;
    status: 'valid' | 'expiring' | 'expired';
    status_label: string;
    teams: string[];
}

const tone = { valid: 'green', expiring: 'amber', expired: 'red' } as const;
const brDate = (iso: string) => iso.split('-').reverse().join('/');

export default function Index({ documents, window_days }: { documents: Row[]; window_days: number }) {
    const expired = documents.filter((row) => row.status === 'expired').length;
    const expiring = documents.length - expired;

    return (
        <PanelLayout>
            <Head title="Documentos a vencer" />
            <PageHeader title="Documentos a vencer" subtitle={`Documentos vencidos e os que vencem nos próximos ${window_days} dias. Os arquivos ficam na ficha de cada pessoa.`} />

            <div className="mb-4 grid grid-cols-2 gap-3 sm:gap-4">
                <StatCard label="Vencidos" value={expired} tone={expired > 0 ? 'red' : 'green'} />
                <StatCard label={`Vencem em ${window_days} dias`} value={expiring} tone={expiring > 0 ? 'amber' : 'green'} />
            </div>

            <DataTable
                caption="Documentos a vencer"
                searchPlaceholder="Buscar por pessoa, equipe ou tipo"
                rows={documents}
                rowKey={(row) => row.id}
                persistKey="panel-documents"
                empty="Nenhum documento vencido ou perto de vencer."
                columns={[
                    {
                        key: 'person',
                        header: 'Pessoa',
                        primary: true,
                        value: (row) => `${row.person} ${row.teams.join(' ')}`,
                        cell: (row) => (
                            <Link href={route('panel.people.edit', row.person_id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                {row.person}
                            </Link>
                        ),
                    },
                    {
                        key: 'teams',
                        header: 'Equipes',
                        value: (row) => row.teams.join(', '),
                        hideBelow: 'lg',
                        cell: (row) => <span className="text-gray-600">{row.teams.length > 0 ? row.teams.join(', ') : '—'}</span>,
                    },
                    {
                        key: 'type',
                        header: 'Documento',
                        value: (row) => row.type_label,
                        filter: true,
                        cell: (row) => <span className="text-gray-700">{row.type_label}</span>,
                    },
                    {
                        key: 'expires_on',
                        header: 'Validade',
                        value: (row) => row.expires_on,
                        cell: (row) => (
                            <span className="text-gray-700">
                                {brDate(row.expires_on)}
                                <span className="block text-xs text-gray-500">{row.days_left >= 0 ? (row.days_left === 0 ? 'vence hoje' : `faltam ${row.days_left} dia(s)`) : `venceu há ${-row.days_left} dia(s)`}</span>
                            </span>
                        ),
                    },
                    {
                        key: 'status',
                        header: 'Situação',
                        value: (row) => row.status_label,
                        filter: true,
                        cell: (row) => <Badge tone={tone[row.status]}>{row.status_label}</Badge>,
                    },
                ]}
            />
        </PanelLayout>
    );
}

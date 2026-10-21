import DataTable from '@/Components/DataTable';
import { Badge, DeleteButton, Empty, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { formatWhen } from '@/lib/statusPage';
import { Head, router } from '@inertiajs/react';

interface Lead {
    id: string;
    name: string;
    email: string;
    organization_name: string | null;
    interest: string | null;
    message: string;
    status: string;
    created_at: string | null;
}

interface Props {
    leads: Lead[];
    statuses: { value: string; label: string }[];
    filter: string | null;
    counts: Record<string, number>;
}

const TONE: Record<string, 'amber' | 'blue' | 'gray'> = { new: 'amber', contacted: 'blue', archived: 'gray' };

export default function LeadsIndex({ leads, statuses, counts }: Props) {
    const setStatus = (lead: Lead, status: string) => router.patch(route('platform.leads.update', lead.id), { status }, { preserveScroll: true });
    const label = (value: string) => statuses.find((status) => status.value === value)?.label ?? value;

    return (
        <PanelLayout>
            <Head title="Contatos da landing" />
            <PageHeader
                title="Contatos da landing page"
                subtitle={`Mensagens enviadas pelo formulário da página do produto. Responda pelo e-mail e atualize a situação. ${statuses.map((status) => `${status.label}: ${counts[status.value] ?? 0}`).join(' · ')}`}
            />

            <DataTable
                caption="Contatos da landing page"
                searchPlaceholder="Buscar por nome, e-mail, organização ou mensagem"
                rows={leads}
                rowKey={(lead) => lead.id}
                persistKey="platform:leads"
                empty={<Empty title="Nenhum contato por aqui">Quando alguém enviar o formulário da landing page, a mensagem aparece nesta lista.</Empty>}
                columns={[
                    {
                        key: 'name',
                        header: 'Contato',
                        primary: true,
                        value: (lead) => `${lead.name} ${lead.email}`,
                        cell: (lead) => (
                            <div data-testid="lead">
                                <div className="font-semibold text-gray-900">{lead.name}</div>
                                <a className="text-sm text-emerald-700 hover:underline" href={`mailto:${lead.email}`}>
                                    {lead.email}
                                </a>
                            </div>
                        ),
                    },
                    { key: 'organization', header: 'Organização', hideBelow: 'lg', value: (lead) => lead.organization_name, cell: (lead) => <span className="text-gray-600">{lead.organization_name ?? '—'}</span> },
                    { key: 'interest', header: 'Assunto', filter: true, value: (lead) => lead.interest, cell: (lead) => (lead.interest ? <Badge>{lead.interest}</Badge> : <span className="text-gray-400">—</span>) },
                    { key: 'status', header: 'Situação', filter: statuses, value: (lead) => label(lead.status), cell: (lead) => <Badge tone={TONE[lead.status] ?? 'gray'}>{label(lead.status)}</Badge> },
                    { key: 'when', header: 'Recebido em', value: (lead) => lead.created_at, cell: (lead) => <span className="text-xs text-gray-500">{lead.created_at ? formatWhen(lead.created_at) : '—'}</span> },
                    { key: 'message', header: 'Mensagem', sortable: false, hideBelow: 'xl', value: (lead) => lead.message, cell: (lead) => <span className="line-clamp-3 whitespace-pre-line text-sm text-gray-800">{lead.message}</span> },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (lead) => (
                            <div className="flex flex-wrap justify-end gap-2">
                                {statuses
                                    .filter((status) => status.value !== lead.status)
                                    .map((status) => (
                                        <button key={status.value} type="button" onClick={() => setStatus(lead, status.value)} className="rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                            Marcar como {status.label.toLowerCase()}
                                        </button>
                                    ))}
                                <DeleteButton href={route('platform.leads.destroy', lead.id)} message={`Remover o contato de ${lead.name}? Os dados pessoais serão apagados.`} />
                            </div>
                        ),
                    },
                ]}
            />
        </PanelLayout>
    );
}

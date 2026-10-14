import { Badge, Card, DeleteButton, Empty, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { formatWhen } from '@/lib/statusPage';
import { Head, Link, router } from '@inertiajs/react';

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

export default function LeadsIndex({ leads, statuses, filter, counts }: Props) {
    const setStatus = (lead: Lead, status: string) => router.patch(route('platform.leads.update', lead.id), { status }, { preserveScroll: true });

    return (
        <PanelLayout>
            <Head title="Contatos da landing" />
            <PageHeader title="Contatos da landing page" subtitle="Mensagens enviadas pelo formulário da página do produto. Responda pelo e-mail e atualize a situação." />

            <div className="mb-4 flex flex-wrap gap-2 text-sm" role="tablist" aria-label="Filtrar por situação">
                <Link href={route('platform.leads.index')} className={`rounded-md border px-3 py-1.5 font-semibold ${filter === null ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50'}`}>
                    Todos
                </Link>
                {statuses.map((status) => (
                    <Link key={status.value} href={route('platform.leads.index', { status: status.value })} className={`rounded-md border px-3 py-1.5 font-semibold ${filter === status.value ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50'}`}>
                        {status.label} ({counts[status.value] ?? 0})
                    </Link>
                ))}
            </div>

            {leads.length === 0 ? (
                <Empty title="Nenhum contato por aqui">Quando alguém enviar o formulário da landing page, a mensagem aparece nesta lista.</Empty>
            ) : (
                <div className="space-y-4">
                    {leads.map((lead) => (
                        <Card key={lead.id}>
                            <div data-testid="lead">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="font-semibold text-gray-900">{lead.name}</h3>
                                        <Badge tone={TONE[lead.status] ?? 'gray'}>{statuses.find((status) => status.value === lead.status)?.label ?? lead.status}</Badge>
                                        {lead.interest && <Badge>{lead.interest}</Badge>}
                                    </div>
                                    {lead.created_at && <span className="text-xs text-gray-500">{formatWhen(lead.created_at)}</span>}
                                </div>
                                <p className="mt-1 text-sm text-gray-600">
                                    <a className="text-emerald-700 hover:underline" href={`mailto:${lead.email}`}>
                                        {lead.email}
                                    </a>
                                    {lead.organization_name && <> · {lead.organization_name}</>}
                                </p>
                                <p className="mt-3 whitespace-pre-line text-sm text-gray-800">{lead.message}</p>
                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    {statuses.filter((status) => status.value !== lead.status).map((status) => (
                                        <button key={status.value} type="button" onClick={() => setStatus(lead, status.value)} className="rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                            Marcar como {status.label.toLowerCase()}
                                        </button>
                                    ))}
                                    <DeleteButton href={route('platform.leads.destroy', lead.id)} message={`Remover o contato de ${lead.name}? Os dados pessoais serão apagados.`} />
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </PanelLayout>
    );
}

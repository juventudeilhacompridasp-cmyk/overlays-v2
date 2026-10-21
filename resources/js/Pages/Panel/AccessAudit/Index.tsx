import DataTable from '@/Components/DataTable';
import { Badge, Card, Empty, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';

interface Entry {
    id: string;
    at: string | null;
    actor: string;
    action: string;
    subject: string | null;
    summary: string;
}

const LABELS: Record<string, string> = {
    'member.created': 'Acesso criado',
    'member.access': 'Acesso alterado',
    'member.overrides': 'Exceções',
    'member.removed': 'Acesso removido',
    'profile.created': 'Perfil criado',
    'profile.updated': 'Perfil alterado',
    'profile.deleted': 'Perfil excluído',
};

export default function Index({ entries }: { entries: Entry[] }) {
    return (
        <PanelLayout>
            <Head title="Auditoria de acesso" />
            <PageHeader title="Auditoria de acesso" subtitle="Quem mudou perfis, papéis e exceções de acesso. Mostra as 200 mudanças mais recentes; não guarda endereço IP." />

            <DataTable
                title={`Mudanças (${entries.length})`}
                caption="Mudanças de acesso"
                searchPlaceholder="Buscar por pessoa, ação ou resumo"
                rows={entries}
                rowKey={(entry) => entry.id}
                persistKey="panel-access-audit"
                empty={<Empty title="Nenhuma mudança registrada">As próximas alterações de acesso aparecem aqui.</Empty>}
                columns={[
                    {
                        key: 'at',
                        header: 'Quando',
                        value: (entry) => entry.at,
                        nowrap: true,
                        cell: (entry) => <span className="text-gray-600">{entry.at ? new Date(entry.at).toLocaleString('pt-BR') : '—'}</span>,
                    },
                    {
                        key: 'action',
                        header: 'Ação',
                        value: (entry) => LABELS[entry.action] ?? entry.action,
                        filter: true,
                        cell: (entry) => <Badge tone="blue">{LABELS[entry.action] ?? entry.action}</Badge>,
                    },
                    { key: 'subject', header: 'Quem foi afetado', primary: true, value: (entry) => entry.subject, cell: (entry) => <span className="font-medium text-gray-900">{entry.subject ?? '—'}</span> },
                    { key: 'actor', header: 'Feito por', value: (entry) => entry.actor, filter: true, cell: (entry) => <span className="text-gray-600">{entry.actor}</span> },
                    { key: 'summary', header: 'O que mudou', sortable: false, value: (entry) => entry.summary, hideBelow: 'lg', cell: (entry) => <span className="text-gray-700">{entry.summary}</span> },
                ]}
            />
        </PanelLayout>
    );
}

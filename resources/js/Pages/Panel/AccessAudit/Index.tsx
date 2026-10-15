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

            <Card title={`Mudanças (${entries.length})`}>
                {entries.length === 0 ? (
                    <Empty title="Nenhuma mudança registrada">As próximas alterações de acesso aparecem aqui.</Empty>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {entries.map((entry) => (
                            <li key={entry.id} className="py-3">
                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    <Badge tone="blue">{LABELS[entry.action] ?? entry.action}</Badge>
                                    {entry.subject && <span className="font-medium text-gray-900">{entry.subject}</span>}
                                    <span className="text-gray-500">
                                        por {entry.actor}
                                        {entry.at && ` · ${new Date(entry.at).toLocaleString('pt-BR')}`}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-gray-700">{entry.summary}</p>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PanelLayout>
    );
}

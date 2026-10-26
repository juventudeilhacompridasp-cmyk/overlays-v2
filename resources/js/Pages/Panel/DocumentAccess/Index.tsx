import DataTable from '@/Components/DataTable';
import { Badge, Empty, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';

interface Entry {
    id: string;
    at: string | null;
    actor: string;
    kind: string;
    kind_label: string;
    label: string;
}

export default function Index({ entries }: { entries: Entry[] }) {
    return (
        <PanelLayout>
            <Head title="Acesso a documentos" />
            <PageHeader
                title="Acesso a documentos"
                subtitle="Quem abriu documentos pessoais (inscrições, atestados e súmulas enviadas). Mostra os 300 acessos mais recentes; guarda por 2 anos, sem endereço IP."
            />

            <DataTable
                title={`Acessos (${entries.length})`}
                caption="Acessos a documentos pessoais"
                searchPlaceholder="Buscar por pessoa, documento ou quem abriu"
                rows={entries}
                rowKey={(entry) => entry.id}
                persistKey="panel-document-access"
                empty={<Empty title="Nenhum acesso registrado">Os próximos documentos abertos aparecem aqui.</Empty>}
                columns={[
                    {
                        key: 'at',
                        header: 'Quando',
                        value: (entry) => entry.at,
                        nowrap: true,
                        cell: (entry) => <span className="text-gray-600">{entry.at ? new Date(entry.at).toLocaleString('pt-BR') : '—'}</span>,
                    },
                    { key: 'actor', header: 'Quem abriu', primary: true, value: (entry) => entry.actor, filter: true, cell: (entry) => <span className="font-medium text-gray-900">{entry.actor}</span> },
                    { key: 'label', header: 'Documento', value: (entry) => entry.label, cell: (entry) => <span className="text-gray-700">{entry.label}</span> },
                    { key: 'kind', header: 'Tipo', value: (entry) => entry.kind_label, filter: true, hideBelow: 'lg', cell: (entry) => <Badge tone="blue">{entry.kind_label}</Badge> },
                ]}
            />
        </PanelLayout>
    );
}

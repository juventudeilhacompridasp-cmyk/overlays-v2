import DataTable from '@/Components/DataTable';
import { Badge, Card, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, Link } from '@inertiajs/react';

interface Item {
    section: string;
    label: string;
    status: 'match' | 'differs' | 'only_sheet' | 'only_system' | 'unclear';
    sheet: string | null;
    system: string | null;
    note: string | null;
}

interface Props {
    upload: { id: string; name: string; game_id: string; home: string | null; away: string | null; division: string; season: string };
    summary: Record<Item['status'], number>;
    sections: { title: string; items: Item[] }[];
    status_labels: Record<Item['status'], string>;
    changes: string[];
    reviewed_by: string | null;
    reviewed_at: string | null;
    notes: string | null;
}

const tone = (status: Item['status']): 'green' | 'red' | 'amber' | 'blue' | 'gray' =>
    status === 'match' ? 'green' : status === 'differs' ? 'red' : status === 'unclear' ? 'gray' : status === 'only_sheet' ? 'amber' : 'blue';

export default function SheetReport({ upload, summary, sections, status_labels, changes, reviewed_by, reviewed_at, notes }: Props) {
    const canOperate = useCan('operate-games');
    const problems = summary.differs + summary.only_sheet + summary.only_system;

    return (
        <PanelLayout>
            <Head title="Conferência da súmula" />
            <PageHeader
                title="Conferência da súmula"
                subtitle={`${upload.home ?? 'A definir'} x ${upload.away ?? 'A definir'} · ${upload.division} · ${upload.season}`}
                breadcrumbs={[
                    { label: 'Súmulas enviadas', href: route('panel.games.uploads', upload.game_id) },
                    { label: 'Conferência' },
                ]}
                actions={
                    <a href={route('panel.games.uploads.report.csv', upload.id)} className="text-sm text-emerald-700 underline">
                        Baixar CSV
                    </a>
                }
            />

            <Card className="mb-6 max-w-4xl">
                <p className="text-sm text-gray-700" data-testid="verdict">
                    {problems === 0 ? 'A súmula enviada confere com o que está lançado no sistema.' : `${problems} ponto(s) para conferir: a súmula e o sistema não dizem a mesma coisa.`}
                </p>
                <div className="mt-3 flex flex-wrap gap-2" data-testid="summary">
                    {(Object.keys(status_labels) as Item['status'][]).map((status) => (
                        <Badge key={status} tone={tone(status)}>
                            {status_labels[status]}: {summary[status]}
                        </Badge>
                    ))}
                </div>
                <p className="mt-3 text-xs text-gray-500">
                    Arquivo: {upload.name}. Revisado por {reviewed_by ?? '—'}
                    {reviewed_at ? ` em ${new Date(reviewed_at).toLocaleString('pt-BR')}` : ''}. O relatório é calculado agora, com o jogo como está hoje, e <strong>não altera o jogo</strong>: para corrigir, reabra o jogo e ajuste a súmula.
                </p>
                {notes && <p className="mt-2 text-xs text-gray-600">Observações da súmula: {notes}</p>}
                {changes.length > 0 && (
                    <details className="mt-2 text-xs text-gray-600">
                        <summary className="cursor-pointer">Correções feitas na leitura ({changes.length})</summary>
                        <ul className="mt-1 list-disc pl-5">
                            {changes.map((change) => (
                                <li key={change}>{change}</li>
                            ))}
                        </ul>
                    </details>
                )}
                {canOperate && (
                    <p className="mt-3 text-sm">
                        <Link href={route('panel.games.uploads.review', upload.id)} className="text-emerald-700 underline">
                            Voltar à revisão
                        </Link>
                    </p>
                )}
            </Card>

            <div className="max-w-5xl space-y-4">
                {sections.map((section) => (
                    <div key={section.title} data-testid="report-section">
                        <DataTable
                            title={section.title}
                            caption={`Conferência: ${section.title}`}
                            searchPlaceholder="Buscar item"
                            rows={section.items}
                            rowKey={(item) => `${item.section}:${item.label}:${item.sheet ?? ''}:${item.system ?? ''}`}
                            pageSize={25}
                            columns={[
                                {
                                    key: 'status',
                                    header: 'Situação',
                                    filter: (Object.keys(status_labels) as Item['status'][]).map((status) => ({ value: status_labels[status], label: status_labels[status] })),
                                    value: (item) => status_labels[item.status],
                                    cell: (item) => <Badge tone={tone(item.status)}>{status_labels[item.status]}</Badge>,
                                },
                                { key: 'label', header: 'Item', primary: true, value: (item) => item.label, cell: (item) => <span className="text-gray-900">{item.label}</span> },
                                { key: 'sheet', header: 'Súmula enviada', value: (item) => item.sheet, cell: (item) => <span className="text-gray-900">{item.sheet ?? '—'}</span> },
                                { key: 'system', header: 'Sistema', value: (item) => item.system, cell: (item) => <span className="text-gray-900">{item.system ?? '—'}</span> },
                                { key: 'note', header: 'Observação', sortable: false, hideBelow: 'lg', value: (item) => item.note, cell: (item) => <span className="text-xs text-gray-500">{item.note ?? ''}</span> },
                            ]}
                        />
                    </div>
                ))}
            </div>
        </PanelLayout>
    );
}

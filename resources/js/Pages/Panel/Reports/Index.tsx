import DataTable from '@/Components/DataTable';
import { Card, Empty, LinkButton, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, router } from '@inertiajs/react';

type Row = Record<string, string>;

interface Report {
    total: number;
    rows: Row[];
}

interface Props {
    seasons: { id: string; name: string }[];
    season_id: string | null;
    limit: number;
    pending: Report;
    rosters: Report;
}

function ReportTable({ report, limit, caption }: { report: Report; limit: number; caption: string }) {
    if (report.rows.length === 0) {
        return <Empty title="Nada por aqui." />;
    }
    const columns = Object.keys(report.rows[0]);

    return (
        <>
            <DataTable
                flush
                className="-mx-5 -mb-5"
                caption={caption}
                searchPlaceholder="Buscar no relatório"
                rows={report.rows}
                rowKey={(row) => JSON.stringify(row)}
                persistKey={`panel-report-${caption}`}
                columns={columns.map((column, index) => ({
                    key: column,
                    header: column,
                    primary: index === 0,
                    value: (row: Row) => row[column],
                    filter: report.rows.length > 1 && new Set(report.rows.map((row) => row[column])).size <= 12 && new Set(report.rows.map((row) => row[column])).size > 1,
                    cell: (row: Row) => row[column] || '—',
                }))}
            />
            {report.total > limit && <p className="mt-8 text-xs text-gray-500">Mostrando {limit} de {report.total}. O CSV traz tudo.</p>}
        </>
    );
}

export default function Index({ seasons, season_id, limit, pending, rosters }: Props) {
    return (
        <PanelLayout>
            <Head title="Relatórios" />
            <PageHeader title="Relatórios" subtitle="Elencos das inscrições confirmadas de uma edição. Nenhum relatório traz o CPF." />
            {seasons.length === 0 || !season_id ? (
                <Empty title="Nenhuma edição cadastrada." />
            ) : (
                <div className="space-y-6">
                    <div className="w-80">
                        <Select value={season_id} onChange={(e) => router.get(route('panel.reports.index'), { season: e.target.value }, { preserveState: true })}>
                            {seasons.map((season) => (
                                <option key={season.id} value={season.id}>
                                    {season.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <Card
                        title={`Pendências de cadastro (${pending.total})`}
                        actions={
                            <a href={route('panel.reports.csv', [season_id, 'pendencias'])} className="text-sm text-emerald-700 hover:underline">
                                Baixar CSV
                            </a>
                        }
                    >
                        <p className="mb-3 text-sm text-gray-600">Integrantes ativos sem data de nascimento, CPF ou foto.</p>
                        <ReportTable report={pending} limit={limit} caption="Pendências de cadastro" />
                    </Card>
                    <Card
                        title={`Elencos aprovados (${rosters.total})`}
                        actions={
                            <a href={route('panel.reports.csv', [season_id, 'elencos'])} className="text-sm text-emerald-700 hover:underline">
                                Baixar CSV
                            </a>
                        }
                    >
                        <ReportTable report={rosters} limit={limit} caption="Elencos aprovados" />
                    </Card>
                </div>
            )}
        </PanelLayout>
    );
}

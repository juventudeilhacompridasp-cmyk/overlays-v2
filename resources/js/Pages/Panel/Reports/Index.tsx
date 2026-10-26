import DataTable from '@/Components/DataTable';
import { Card, Empty, Field, FilterBar, PageHeader, Select, StatCard } from '@/Components/Panel/ui';
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

function CsvLink({ href, label }: { href: string; label: string }) {
    return (
        <a href={href} className="inline-flex min-h-11 items-center justify-center rounded-md px-3 py-2 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50 sm:min-h-0">
            {label}
        </a>
    );
}

export default function Index({ seasons, season_id, limit, pending, rosters }: Props) {
    return (
        <PanelLayout>
            <Head title="Relatórios" />
            <PageHeader title="Relatórios" subtitle="Pendências de cadastro e elencos aprovados de uma edição, na tela e em CSV. Nenhum relatório traz o CPF." />
            {seasons.length === 0 || !season_id ? (
                <Empty title="Nenhuma edição cadastrada." />
            ) : (
                <div className="space-y-6">
                    <FilterBar label="Edição do relatório">
                        <Field label="Edição" htmlFor="report-season">
                            <Select id="report-season" value={season_id} onChange={(e) => router.get(route('panel.reports.index'), { season: e.target.value }, { preserveState: true })}>
                                {seasons.map((season) => (
                                    <option key={season.id} value={season.id}>
                                        {season.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </FilterBar>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                        <StatCard label="Pendências de cadastro" value={pending.total} tone={pending.total > 0 ? 'amber' : 'green'} hint={pending.total > 0 ? 'Sem nascimento, CPF ou foto' : 'Cadastros em dia'} />
                        <StatCard label="Integrantes em elencos aprovados" value={rosters.total} tone="blue" />
                    </div>

                    <Card
                        title={`Pendências de cadastro (${pending.total})`}
                        actions={<CsvLink href={route('panel.reports.csv', [season_id, 'pendencias'])} label="Baixar CSV" />}
                    >
                        <p className="mb-3 text-sm text-gray-600">Integrantes ativos sem data de nascimento, CPF ou foto.</p>
                        <ReportTable report={pending} limit={limit} caption="Pendências de cadastro" />
                    </Card>
                    <Card
                        title={`Elencos aprovados (${rosters.total})`}
                        actions={<CsvLink href={route('panel.reports.csv', [season_id, 'elencos'])} label="Baixar CSV" />}
                    >
                        <ReportTable report={rosters} limit={limit} caption="Elencos aprovados" />
                    </Card>
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-950">
                        <p>Relatórios em PDF de cada partida ficam no menu <b>Relatórios em PDF</b> da súmula do jogo.</p>
                        <a href={route('panel.reports.sponsor-pdf', season_id)} className="inline-flex min-h-11 items-center rounded-md bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">Baixar relatório institucional</a>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}

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

function Table({ report, limit }: { report: Report; limit: number }) {
    if (report.rows.length === 0) {
        return <Empty title="Nada por aqui." />;
    }
    const columns = Object.keys(report.rows[0]);

    return (
        <>
            <div className="-m-5 overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-100 text-sm">
                    <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            {columns.map((column) => (
                                <th key={column} className="px-4 py-2">
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {report.rows.map((row, index) => (
                            <tr key={index}>
                                {columns.map((column) => (
                                    <td key={column} className="px-4 py-2">
                                        {row[column] || '—'}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
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
                        <Table report={pending} limit={limit} />
                    </Card>
                    <Card
                        title={`Elencos aprovados (${rosters.total})`}
                        actions={
                            <a href={route('panel.reports.csv', [season_id, 'elencos'])} className="text-sm text-emerald-700 hover:underline">
                                Baixar CSV
                            </a>
                        }
                    >
                        <Table report={rosters} limit={limit} />
                    </Card>
                </div>
            )}
        </PanelLayout>
    );
}

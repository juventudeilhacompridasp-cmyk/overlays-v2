import DataTable, { serverTable } from '@/Components/DataTable';
import { Badge, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Paginated, TableFilters } from '@/types/panel';
import { Head, Link, router } from '@inertiajs/react';

interface Entry {
    id: string;
    at: string;
    actor: string;
    role: string | null;
    area: string;
    kind: string;
    summary: string;
    subject: string | null;
    game_id: string | null;
    outcome: 'ok' | 'refused' | 'failed';
    outcome_label: string;
    fields: string[];
    changes: Record<string, [unknown, unknown]>;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    entries: Paginated<Entry>;
    filters: TableFilters;
    actors: Option[];
    areas: Option[];
    outcomes: Option[];
    kinds: Option[];
    game: { id: string; label: string | null } | null;
    period: { from: string | null; to: string | null };
    retention_days: number;
}

const TONE = { ok: 'green', refused: 'amber', failed: 'red' } as const;

const show = (value: unknown): string => (value === null || value === undefined || value === '' ? '—' : String(value));

/** Querystring dos filtros que a exportação repete (os mesmos da tela). */
function exportUrl(filters: TableFilters, game: Props['game'], period: Props['period']): string {
    const params = new URLSearchParams();
    if (filters.q) params.set('q', filters.q);
    Object.entries(filters.values ?? {}).forEach(([key, value]) => params.set(`filter[${key}]`, value));
    if (game) params.set('game', game.id);
    if (period.from) params.set('from', period.from);
    if (period.to) params.set('to', period.to);
    const query = params.toString();

    return route('panel.activity-log.export') + (query ? `?${query}` : '');
}

export default function ActivityLogIndex({ entries, filters, actors, areas, outcomes, kinds, game, period, retention_days }: Props) {
    const setPeriod = (key: 'from' | 'to', value: string) => {
        const query: Record<string, string> = {};
        if (game) query.game = game.id;
        const next = { ...period, [key]: value || null };
        if (next.from) query.from = next.from;
        if (next.to) query.to = next.to;
        Object.entries(filters.values ?? {}).forEach(([name, filter]) => (query[`filter[${name}]`] = filter));
        router.get(route('panel.activity-log.index'), query, { preserveScroll: true, replace: true });
    };

    return (
        <PanelLayout>
            <Head title="Registro de atividades" />
            <PageHeader
                title="Registro de atividades"
                subtitle={`Auditoria do que cada usuário fez: ações, recusas e mudanças de jogos e lances, guardadas no banco por ${retention_days} dias. Nunca guarda IP, CPF, senhas nem valores digitados.`}
                actions={
                    <a href={exportUrl(filters, game, period)} className="inline-flex min-h-11 items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:min-h-0">
                        Baixar CSV
                    </a>
                }
            />

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <label className="flex flex-col text-xs font-medium text-gray-600">
                    De
                    <input type="date" value={period.from ?? ''} onChange={(event) => setPeriod('from', event.target.value)} className="mt-1 min-h-11 rounded-md border border-gray-300 px-2 py-1 text-sm sm:min-h-0" />
                </label>
                <label className="flex flex-col text-xs font-medium text-gray-600">
                    Até
                    <input type="date" value={period.to ?? ''} onChange={(event) => setPeriod('to', event.target.value)} className="mt-1 min-h-11 rounded-md border border-gray-300 px-2 py-1 text-sm sm:min-h-0" />
                </label>
                {game && (
                    <div className="flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        Só o jogo: <strong>{game.label ?? game.id}</strong>
                        <Link href={route('panel.activity-log.index')} className="font-semibold underline">
                            limpar
                        </Link>
                    </div>
                )}
            </div>

            <DataTable
                caption="Registro de atividades"
                searchPlaceholder="Buscar por pessoa, ação ou jogo"
                rows={entries.data}
                rowKey={(entry) => entry.id}
                server={serverTable(route('panel.activity-log.index'), entries, filters, { sort: 'when', dir: 'desc', per_page: 25 }, ['entries', 'filters', 'game', 'period'])}
                empty="Nenhuma atividade registrada com estes filtros."
                columns={[
                    {
                        key: 'when',
                        header: 'Quando',
                        nowrap: true,
                        value: (entry) => entry.at,
                        cell: (entry) => <span className="text-gray-600">{new Date(entry.at).toLocaleString('pt-BR')}</span>,
                    },
                    {
                        key: 'actor',
                        header: 'Quem',
                        primary: true,
                        value: (entry) => entry.actor,
                        filter: actors,
                       
                        cell: (entry) => (
                            <div>
                                <div className="font-medium text-gray-900">{entry.actor}</div>
                                {entry.role && <div className="text-xs text-gray-500">{entry.role}</div>}
                            </div>
                        ),
                    },
                    {
                        key: 'summary',
                        header: 'O que foi feito',
                        value: (entry) => entry.summary,
                        cell: (entry) => (
                            <div className="text-gray-800">
                                <div>{entry.summary}</div>
                                {entry.subject &&
                                    (entry.game_id ? (
                                        <Link href={route('panel.activity-log.index', { game: entry.game_id })} className="text-xs text-emerald-700 hover:underline">
                                            {entry.subject}
                                        </Link>
                                    ) : (
                                        <div className="text-xs text-gray-500">{entry.subject}</div>
                                    ))}
                                {Object.keys(entry.changes).length > 0 && (
                                    <ul className="mt-1 text-xs text-gray-600">
                                        {Object.entries(entry.changes).map(([field, pair]) => (
                                            <li key={field}>
                                                <code>{field}</code>: {show(pair[0])} → <strong>{show(pair[1])}</strong>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {entry.fields.length > 0 && <div className="mt-1 text-xs text-gray-400">Campos: {entry.fields.join(', ')}</div>}
                            </div>
                        ),
                    },
                    { key: 'area', header: 'Área', hideBelow: 'lg', sortable: false, value: (entry) => entry.area, filter: areas, cell: (entry) => <span className="text-gray-600">{entry.area}</span> },
                    { key: 'kind', header: 'Tipo', hideBelow: 'xl', sortable: false, value: (entry) => entry.kind, filter: kinds, cell: (entry) => <Badge>{entry.kind}</Badge> },
                    { key: 'outcome', header: 'Resultado', value: (entry) => entry.outcome_label, filter: outcomes, cell: (entry) => <Badge tone={TONE[entry.outcome]}>{entry.outcome_label}</Badge> },
                ]}
            />
        </PanelLayout>
    );
}

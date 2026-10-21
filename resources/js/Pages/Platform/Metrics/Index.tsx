import DataTable from '@/Components/DataTable';
import { Card, Empty, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Day {
    date: string;
    visits: number;
    leads: number;
}

interface Item {
    key: string;
    label: string;
    count: number;
    percent: number | null;
}

interface Props {
    enabled: boolean;
    periods: number[];
    summary: {
        days: number;
        from: string;
        to: string;
        daily: Day[];
        totals: { visits: number; leads: number; conversion: number | null };
        breakdown: Record<string, { label: string; items: Item[] }>;
    };
}

const shortDate = (date: string) => date.split('-').reverse().slice(0, 2).join('/');
const number = (value: number) => value.toLocaleString('pt-BR');
const percent = (value: number | null) => (value === null ? '—' : `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`);

/** Marca "bonita" para o topo do eixo: 4, 5, 8, 10, 20, 50, 100... */
function niceMax(value: number): number {
    if (value <= 4) return 4;

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const step = [1, 2, 5, 10].find((candidate) => candidate * magnitude >= value) ?? 10;

    return step * magnitude;
}

/** Stat tile: rótulo, valor e uma linha de apoio. */
function Tile({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <p className="text-sm text-gray-500">{label}</p>
            <p className="mt-1 text-3xl font-semibold text-gray-900">{value}</p>
            {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
        </div>
    );
}

/** Visitas por dia: série única, colunas finas (ponta de 4 px), grade de 1 px, rótulo só no pico e dica por coluna (hover e foco). */
function DailyChart({ daily }: { daily: Day[] }) {
    const [active, setActive] = useState<number | null>(null);
    const max = Math.max(...daily.map((day) => day.visits), 0);
    const top = niceMax(max);
    const peak = max > 0 ? daily.findIndex((day) => day.visits === max) : -1;
    const ticks = [top, top / 2, 0];

    return (
        <div>
            <div className="flex gap-2">
                <ul className="flex h-44 flex-col justify-between pb-0 text-right text-xs text-gray-500" aria-hidden="true">
                    {ticks.map((tick) => (
                        <li key={tick} className="leading-none">{number(tick)}</li>
                    ))}
                </ul>
                <div className="relative h-44 flex-1">
                    {ticks.map((tick, index) => (
                        <div key={tick} className="pointer-events-none absolute inset-x-0 border-t border-gray-200" style={{ top: `${(index / (ticks.length - 1)) * 100}%` }} />
                    ))}
                    <ol className="absolute inset-0 flex items-end gap-[2px]" aria-label="Visitas por dia">
                        {daily.map((day, index) => (
                            <li key={day.date} className="relative flex h-full min-w-0 flex-1 items-end justify-center" onMouseEnter={() => setActive(index)} onMouseLeave={() => setActive(null)} onFocus={() => setActive(index)} onBlur={() => setActive(null)} tabIndex={0} aria-label={`${shortDate(day.date)}: ${number(day.visits)} ${day.visits === 1 ? 'visita' : 'visitas'}`}>
                                <span className={`block w-full max-w-6 rounded-t-[4px] ${active === index ? 'bg-emerald-700' : 'bg-emerald-600'}`} style={{ height: `${(day.visits / top) * 100}%` }} />
                                {index === peak && <span className="pointer-events-none absolute -top-5 left-1/2 -translate-x-1/2 text-xs font-semibold text-gray-900">{number(day.visits)}</span>}
                                {active === index && (
                                    <span role="tooltip" className="pointer-events-none absolute bottom-full z-10 mb-6 -translate-y-1 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white shadow" style={{ left: index > daily.length / 2 ? 'auto' : '50%', right: index > daily.length / 2 ? '0' : 'auto', transform: index > daily.length / 2 ? undefined : 'translateX(-50%)' }}>
                                        <strong className="block text-sm">{number(day.visits)} {day.visits === 1 ? 'visita' : 'visitas'}</strong>
                                        <span className="text-white/70">{shortDate(day.date)}{day.leads > 0 && ` · ${day.leads} ${day.leads === 1 ? 'contato' : 'contatos'}`}</span>
                                    </span>
                                )}
                            </li>
                        ))}
                    </ol>
                </div>
            </div>
            <div className="ml-10 mt-1 flex justify-between text-xs text-gray-500" aria-hidden="true">
                <span>{shortDate(daily[0].date)}</span>
                <span>{shortDate(daily[Math.floor(daily.length / 2)].date)}</span>
                <span>{shortDate(daily[daily.length - 1].date)}</span>
            </div>
            <details className="mt-4">
                <summary className="cursor-pointer text-sm font-medium text-emerald-700">Ver como tabela</summary>
                <div className="mt-2">
                    <DataTable
                        caption="Visitas e contatos por dia"
                        layout="table"
                        searchable={false}
                        pageSize={10}
                        rows={[...daily].reverse()}
                        rowKey={(day) => day.date}
                        columns={[
                            { key: 'date', header: 'Dia', value: (day) => day.date, cell: (day) => <span className="text-gray-700">{shortDate(day.date)}</span> },
                            { key: 'visits', header: 'Visitas', align: 'right', value: (day) => day.visits, cell: (day) => <span className="tabular-nums">{number(day.visits)}</span> },
                            { key: 'leads', header: 'Contatos', align: 'right', value: (day) => day.leads, cell: (day) => <span className="tabular-nums">{number(day.leads)}</span> },
                        ]}
                    />
                </div>
            </details>
        </div>
    );
}

/** Quebra por dimensão: barras horizontais finas de uma cor só, com o valor na ponta; rótulos em tinta de texto. */
function Breakdown({ title, items }: { title: string; items: Item[] }) {
    const max = Math.max(...items.map((item) => item.count), 1);

    return (
        <Card title={title}>
            <ul className="space-y-3">
                {items.map((item) => (
                    <li key={item.key}>
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="text-gray-700">{item.label}</span>
                            <span className="tabular-nums text-gray-900"><strong>{number(item.count)}</strong> <span className="text-gray-500">· {percent(item.percent)}</span></span>
                        </div>
                        <div className="mt-1 h-2 rounded-r-[4px] bg-gray-100" aria-hidden="true">
                            <div className="h-2 rounded-r-[4px] bg-emerald-600" style={{ width: `${(item.count / max) * 100}%` }} />
                        </div>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

export default function MetricsIndex({ enabled, periods, summary }: Props) {
    const { totals, daily, breakdown } = summary;

    return (
        <PanelLayout>
            <Head title="Medição da landing" />
            <PageHeader title="Medição da landing page" subtitle="Contagem anônima de acessos a /produto, só de quem permitiu. Sem cookies de rastreio, IP ou identificação: guardamos apenas contadores por dia." />

            {!enabled && <p role="status" className="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">A medição está desligada (PLATFORM_LANDING_ANALYTICS). Nada novo é coletado e o aviso de consentimento não aparece.</p>}

            <div className="mb-4 flex flex-wrap gap-2 text-sm" role="tablist" aria-label="Período">
                {periods.map((period) => (
                    <Link key={period} href={route('platform.metrics.index', { dias: period })} preserveScroll className={`rounded-md border px-3 py-1.5 font-semibold ${summary.days === period ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50'}`}>
                        Últimos {period} dias
                    </Link>
                ))}
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <Tile label="Visitas medidas" value={number(totals.visits)} hint={`${shortDate(summary.from)} a ${shortDate(summary.to)}`} />
                <Tile label="Contatos enviados" value={number(totals.leads)} hint="Todos os envios do formulário no período" />
                <Tile label="Contatos por visita" value={percent(totals.conversion)} hint="Aproximado: o formulário conta quem não permitiu a medição" />
            </div>

            <Card title="Visitas por dia" className="mb-6">
                {totals.visits === 0 ? <Empty title="Ainda sem visitas medidas">Quando alguém permitir a medição na landing page, as visitas aparecem aqui.</Empty> : <DailyChart daily={daily} />}
            </Card>

            <div className="grid gap-6 md:grid-cols-2">
                {(['source', 'device', 'section', 'cta'] as const).map((key) => (
                    <Breakdown key={key} title={breakdown[key].label} items={breakdown[key].items} />
                ))}
            </div>
            <p className="mt-4 text-xs text-gray-500">Cada visita conta uma vez por sessão do navegador. Os percentuais são parcelas das visitas medidas.</p>
        </PanelLayout>
    );
}

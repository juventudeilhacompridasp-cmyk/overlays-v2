import { arcPath, donutSegments, formColor, niceMax, ReportBlock as Block, share, shortNumber } from '@/lib/report';
import { CSSProperties, ReactNode } from 'react';

/**
 * Desenho de um bloco do relatório (o mesmo documento que vira PDF): prévia no painel (`tone="paper"`) e overlay do OBS
 * (`tone="dark"`/`"light"`). Cores fixas (não seguem o tema do painel) e animações só de transform/opacity.
 */
export type ReportTone = 'paper' | 'dark' | 'light';

const TONES: Record<ReportTone, { ink: string; muted: string; soft: string; line: string; track: string }> = {
    paper: { ink: '#0f172a', muted: '#64748b', soft: '#f1f5f9', line: '#e2e8f0', track: '#e2e8f0' },
    light: { ink: '#0f172a', muted: '#475569', soft: '#f1f5f9', line: '#e2e8f0', track: '#e2e8f0' },
    dark: { ink: '#ffffff', muted: 'rgba(255,255,255,0.68)', soft: 'rgba(255,255,255,0.08)', line: 'rgba(255,255,255,0.14)', track: 'rgba(255,255,255,0.12)' },
};

export function ReportIcon({ path, color, size = 16, className = '' }: { path: string | undefined; color: string; size?: number; className?: string }) {
    if (!path) return null;

    return (
        <svg viewBox="0 0 24 24" width={size} height={size} className={className} aria-hidden="true">
            <path d={path} fill={color} />
        </svg>
    );
}

function delay(index: number, animate: boolean, step = 70): CSSProperties | undefined {
    return animate ? { animationDelay: `${index * step}ms` } : undefined;
}

function Title({ children, tone }: { children: ReactNode; tone: ReportTone }) {
    return <div className="mb-2 text-[11px] font-bold uppercase tracking-[0.14em]" style={{ color: TONES[tone].muted }}>{children}</div>;
}

export default function ReportBlock({ block, icons, accent, tone, animate = false }: { block: Block; icons: Record<string, string>; accent: string; tone: ReportTone; animate?: boolean }) {
    const t = TONES[tone];
    const rise = animate ? 'stats-in-rise' : '';

    switch (block.type) {
        case 'kpis':
            return (
                <div className="grid gap-3" style={{ gridTemplateColumns: `repeat(${Math.max(1, Math.min(4, block.items.length))}, minmax(0, 1fr))` }}>
                    {block.items.map((item, index) => {
                        const color = item.color ?? accent;

                        return (
                            <div key={index} className={`relative overflow-hidden rounded-xl p-3 pl-4 ${rise}`} style={{ background: t.soft, ...delay(index, animate) }}>
                                <span className="absolute inset-y-0 left-0 w-1" style={{ background: color }} />
                                <div className="flex items-center gap-2.5">
                                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full" style={{ background: color }}><ReportIcon path={icons[item.icon]} color="#ffffff" size={16} /></span>
                                    <span className="truncate text-2xl font-black tabular-nums" style={{ color: t.ink }}>{item.value}</span>
                                </div>
                                <div className="mt-1.5 truncate text-xs" style={{ color: t.muted }}>{item.label}</div>
                                {item.progress !== undefined && item.progress !== null ? (
                                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full" style={{ background: t.track }}>
                                        <div className={`h-full origin-left rounded-full ${animate ? 'report-grow-x' : ''}`} style={{ width: `${item.progress}%`, background: color }} />
                                    </div>
                                ) : item.hint ? <div className="truncate text-[10px]" style={{ color: t.muted }}>{item.hint}</div> : null}
                            </div>
                        );
                    })}
                </div>
            );

        case 'versus':
            return (
                <div>
                    {block.title && <Title tone={tone}>{block.title}</Title>}
                    <div className="mb-2 flex items-center justify-between gap-4 text-sm font-bold" style={{ color: t.ink }}>
                        <span className="flex min-w-0 items-center gap-2"><span className="h-3 w-3 shrink-0 rounded-full" style={{ background: block.home.color }} /><span className="truncate">{block.home.name}</span></span>
                        <span className="flex min-w-0 items-center gap-2"><span className="truncate">{block.away.name}</span><span className="h-3 w-3 shrink-0 rounded-full" style={{ background: block.away.color }} /></span>
                    </div>
                    <div className="space-y-2.5">
                        {block.rows.map((row, index) => {
                            const max = Math.max(1, row.home, row.away);

                            return (
                                <div key={row.label} className={rise} style={delay(index, animate)}>
                                    <div className="mb-1 grid grid-cols-[3rem_1fr_3rem] items-end text-center">
                                        <span className="text-left text-lg font-black tabular-nums" style={{ color: row.home >= row.away ? t.ink : t.muted }}>{row.home}{row.suffix}</span>
                                        <span className="text-[11px] font-bold uppercase tracking-wider" style={{ color: t.muted }}>{row.label}</span>
                                        <span className="text-right text-lg font-black tabular-nums" style={{ color: row.away >= row.home ? t.ink : t.muted }}>{row.away}{row.suffix}</span>
                                    </div>
                                    <div className="flex h-2.5 gap-1.5">
                                        <div className="flex flex-1 justify-end overflow-hidden rounded-full" style={{ background: t.track }}>
                                            <span className={`h-full origin-right rounded-full ${animate ? 'report-grow-x' : ''}`} style={{ width: `${share(row.home, max) * 100}%`, background: block.home.color }} />
                                        </div>
                                        <div className="flex flex-1 overflow-hidden rounded-full" style={{ background: t.track }}>
                                            <span className={`h-full origin-left rounded-full ${animate ? 'report-grow-x' : ''}`} style={{ width: `${share(row.away, max) * 100}%`, background: block.away.color }} />
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            );

        case 'columns': {
            const max = niceMax(Math.max(1, ...block.categories.flatMap((category) => category.values)));

            return (
                <div>
                    <Title tone={tone}>{block.title}</Title>
                    <div className="relative h-44 pl-8">
                        {[0, 1, 2, 3, 4].map((step) => (
                            <div key={step} className="absolute left-8 right-0 border-t text-[10px]" style={{ bottom: `${step * 25}%`, borderColor: t.line }}>
                                <span className="absolute -left-8 -top-2 w-6 text-right tabular-nums" style={{ color: t.muted }}>{shortNumber((max * step) / 4)}</span>
                            </div>
                        ))}
                        <div className="relative flex h-full items-end">
                            {block.categories.map((category, index) => (
                                <div key={index} className="flex h-full flex-1 items-end justify-center gap-1 px-1">
                                    {category.values.map((value, s) => (
                                        <div key={s} className="relative flex h-full w-full max-w-[1.6rem] items-end">
                                            <div className={`relative w-full origin-bottom rounded-t ${animate ? 'report-grow-y' : ''}`} style={{ height: `${share(value, max) * 100}%`, background: block.series[s]?.color ?? accent, ...delay(index, animate, 50) }}>
                                                {value > 0 && <span className="absolute -top-4 left-1/2 -translate-x-1/2 text-[10px] font-bold tabular-nums" style={{ color: t.ink }}>{shortNumber(value)}</span>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="flex pl-8">
                        {block.categories.map((category, index) => <div key={index} className="flex-1 truncate px-0.5 pt-1 text-center text-[10px]" style={{ color: t.muted }}>{category.label}</div>)}
                    </div>
                    <div className="mt-2 flex flex-wrap gap-4 pl-8 text-xs" style={{ color: t.ink }}>
                        {block.series.map((item) => <span key={item.label} className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm" style={{ background: item.color }} />{item.label}</span>)}
                    </div>
                </div>
            );
        }

        case 'donut': {
            const segments = donutSegments(block.parts);

            return (
                <div>
                    <Title tone={tone}>{block.title}</Title>
                    <div className="flex items-center gap-5">
                        <svg viewBox="0 0 120 120" className={`h-32 w-32 shrink-0 ${animate ? 'report-pop' : ''}`} role="img" aria-label={`${block.title}: ${block.total} ${block.total_label}`}>
                            {segments.length === 0 && <circle cx="60" cy="60" r="48" fill="none" stroke={t.track} strokeWidth="22" />}
                            {segments.map((segment, index) => <path key={index} d={arcPath(60, 60, 58, 36, segment.from, segment.to)} fill={segment.color} />)}
                            <text x="60" y="62" textAnchor="middle" fontSize="20" fontWeight="900" fill={t.ink}>{shortNumber(block.total)}</text>
                            <text x="60" y="78" textAnchor="middle" fontSize="9" fill={t.muted}>{block.total_label}</text>
                        </svg>
                        <ul className="min-w-0 flex-1 space-y-1.5 text-sm">
                            {block.parts.map((part, index) => (
                                <li key={part.label} className={`flex items-center gap-2 border-b pb-1 ${rise}`} style={{ borderColor: t.line, color: t.ink, ...delay(index, animate) }}>
                                    <span className="h-3 w-3 shrink-0 rounded-sm" style={{ background: part.color }} />
                                    <span className="min-w-0 flex-1 truncate">{part.label}</span>
                                    <span className="font-black tabular-nums">{shortNumber(part.value)}</span>
                                    <span className="w-11 rounded-full px-1.5 text-center text-[11px] font-bold tabular-nums" style={{ background: t.soft, color: t.muted }}>{Math.round(share(part.value, block.total) * 100)}%</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            );
        }

        case 'ranking': {
            const max = Math.max(1, ...block.items.map((item) => item.value));

            return (
                <div>
                    <Title tone={tone}>{block.title}</Title>
                    <ol className="space-y-1">
                        {block.items.map((item, index) => (
                            <li key={index} className={`grid grid-cols-[1.75rem_minmax(0,1fr)_minmax(0,0.8fr)_2.5rem] items-center gap-3 rounded-lg px-2 py-1.5 ${rise}`} style={{ background: index % 2 === 0 ? t.soft : 'transparent', ...delay(index, animate) }}>
                                <span className="grid h-6 w-6 place-items-center rounded-full text-xs font-black" style={{ background: item.position <= 3 ? accent : t.track, color: item.position <= 3 ? '#ffffff' : t.ink }}>{item.position}</span>
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-bold" style={{ color: t.ink }}>{item.label}</span>
                                    {item.sub && <span className="block truncate text-[11px]" style={{ color: t.muted }}>{item.sub}</span>}
                                </span>
                                <span className="h-2 overflow-hidden rounded-full" style={{ background: t.track }}>
                                    <span className={`block h-full origin-left rounded-full ${animate ? 'report-grow-x' : ''}`} style={{ width: `${share(item.value, max) * 100}%`, background: item.color ?? accent }} />
                                </span>
                                <span className="text-right text-lg font-black tabular-nums" style={{ color: t.ink }}>{shortNumber(item.value)}</span>
                            </li>
                        ))}
                    </ol>
                </div>
            );
        }

        case 'table': {
            const total = block.columns.reduce((sum, column) => sum + column.width, 0) || 1;

            return (
                <div>
                    {block.title && <Title tone={tone}>{block.title}</Title>}
                    <table className="w-full table-fixed border-collapse text-sm">
                        <colgroup>{block.columns.map((column) => <col key={column.key} style={{ width: `${(column.width / total) * 100}%` }} />)}</colgroup>
                        <thead>
                            <tr style={{ background: tone === 'dark' ? 'rgba(255,255,255,0.14)' : '#0f172a' }}>
                                {block.columns.map((column) => <th key={column.key} scope="col" className="px-2 py-1.5 text-[11px] font-bold uppercase tracking-wide text-white" style={{ textAlign: column.align }}>{column.label}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {block.rows.map((row, index) => (
                                <tr key={index} className={rise} style={{ background: row.highlight ? `${accent}26` : index % 2 === 1 ? t.soft : 'transparent', boxShadow: row.highlight ? `inset 3px 0 0 ${accent}` : undefined, color: t.ink, ...delay(index, animate, 40) }}>
                                    {block.columns.map((column) => <td key={column.key} className={`truncate border-b px-2 py-1 tabular-nums ${row.highlight ? 'font-bold' : ''}`} style={{ textAlign: column.align, borderColor: t.line }}>{row.cells[column.key] ?? ''}</td>)}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
        }

        case 'timeline':
            return (
                <ol className="relative">
                    <span className="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2" style={{ background: t.line }} />
                    {block.items.map((item, index) => {
                        if (item.side === null) {
                            return (
                                <li key={index} className={`relative my-1.5 flex justify-center ${rise}`} style={delay(index, animate)}>
                                    <span className="flex items-center gap-1.5 rounded-full border px-3 py-0.5 text-xs font-bold" style={{ background: tone === 'dark' ? '#1e293b' : '#f8fafc', borderColor: t.line, color: t.muted }}>
                                        <ReportIcon path={icons[item.icon]} color={item.color} size={12} />{item.title}{item.detail ? ` · ${item.detail}` : ''}
                                    </span>
                                </li>
                            );
                        }

                        const home = item.side === 'home';
                        const text = (
                            <span className={`min-w-0 ${home ? 'text-right' : 'text-left'}`}>
                                <span className="block truncate text-sm font-bold" style={{ color: t.ink }}>{item.title}</span>
                                {item.detail && <span className="block truncate text-[11px]" style={{ color: t.muted }}>{item.detail}</span>}
                            </span>
                        );
                        const icon = <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full" style={{ background: item.color }}><ReportIcon path={icons[item.icon]} color="#ffffff" size={14} /></span>;

                        return (
                            <li key={index} className={`relative grid grid-cols-[1fr_3.5rem_1fr] items-center gap-2 py-1 ${rise}`} style={delay(index, animate)}>
                                <span className="flex min-w-0 items-center justify-end gap-2">{home && <>{text}{icon}</>}</span>
                                <span className="relative mx-auto rounded-full bg-[#0f172a] px-2 py-0.5 text-xs font-black tabular-nums text-white">{item.minute ?? '—'}</span>
                                <span className="flex min-w-0 items-center gap-2">{!home && <>{icon}{text}</>}</span>
                            </li>
                        );
                    })}
                </ol>
            );

        case 'form':
            return (
                <div>
                    <Title tone={tone}>{block.title}</Title>
                    <ul>
                        {block.rows.map((row, index) => (
                            <li key={row.label} className={`flex items-center gap-2 border-b py-1.5 ${rise}`} style={{ borderColor: t.line, ...delay(index, animate) }}>
                                <span className="h-3 w-3 shrink-0 rounded-full" style={{ background: row.color }} />
                                <span className="min-w-0 flex-1 truncate text-sm font-bold" style={{ color: t.ink }}>{row.label}</span>
                                {row.results.length === 0 && <span className="text-xs" style={{ color: t.muted }}>sem jogos</span>}
                                {row.results.map((result, r) => <span key={r} className="grid h-6 w-6 place-items-center rounded-full text-[11px] font-black text-white" style={{ background: formColor(result) }}>{result}</span>)}
                            </li>
                        ))}
                    </ul>
                </div>
            );

        case 'list':
            return (
                <div>
                    <Title tone={tone}>{block.title}</Title>
                    <ul className="space-y-1.5">
                        {block.items.map((item, index) => (
                            <li key={index} className={`flex items-center gap-3 rounded-lg px-3 py-1.5 ${rise}`} style={{ background: t.soft, ...delay(index, animate) }}>
                                {item.icon && <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full" style={{ background: item.color ?? accent }}><ReportIcon path={icons[item.icon]} color="#ffffff" size={14} /></span>}
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-bold" style={{ color: t.ink }}>{item.title}</span>
                                    {item.detail && <span className="block truncate text-[11px]" style={{ color: t.muted }}>{item.detail}</span>}
                                </span>
                                {item.value && <span className="shrink-0 rounded-full bg-[#0f172a] px-3 py-0.5 text-sm font-black tabular-nums text-white">{item.value}</span>}
                            </li>
                        ))}
                    </ul>
                </div>
            );

        default:
            return <p className="rounded-lg border px-4 py-3 text-sm" style={{ borderColor: t.line, background: t.soft, color: t.muted }}>{block.text}</p>;
    }
}

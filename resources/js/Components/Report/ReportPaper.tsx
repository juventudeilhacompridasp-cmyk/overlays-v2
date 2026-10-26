import ReportBlock, { ReportIcon } from '@/Components/Report/ReportBlock';
import { ReportDocument, ReportHeroSide } from '@/lib/report';

function Badge({ side }: { side: ReportHeroSide }) {
    return (
        <div className="flex min-w-0 flex-col items-center gap-2">
            <span className="grid h-16 w-16 place-items-center overflow-hidden rounded-full border-4 bg-[#ffffff] sm:h-20 sm:w-20" style={{ borderColor: side.color }}>
                {side.logo_url ? <img src={side.logo_url} alt="" className="h-3/4 w-3/4 object-contain" /> : <span className="text-lg font-black" style={{ color: side.color }}>{side.short.slice(0, 3).toUpperCase()}</span>}
            </span>
            <span className="max-w-full truncate text-center text-sm font-bold text-white">{side.name}</span>
        </div>
    );
}

/** Prévia do relatório como no PDF (capa escura + seções), com as mesmas peças do overlay. Cores fixas nos dois temas. */
export default function ReportPaper({ document }: { document: ReportDocument }) {
    const { icons, accent, hero } = document;

    return (
        <article className="overflow-hidden rounded-xl bg-[#ffffff] text-neutral-900 shadow-sm ring-1 ring-black/5" aria-label={`${document.title}: ${document.subtitle}`}>
            <header className="relative overflow-hidden bg-[#0f172a] px-5 pb-6 pt-5 sm:px-8">
                <span className="pointer-events-none absolute -right-16 top-0 h-full w-72 skew-x-[-20deg] opacity-20" style={{ background: accent }} />
                <div className="relative flex items-center justify-between gap-3">
                    <span className="truncate text-[11px] font-bold uppercase tracking-widest text-neutral-300">{document.organization}</span>
                    <span className="shrink-0 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white" style={{ background: accent }}>{document.title}</span>
                </div>
                {hero ? (
                    <div className="relative mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                        <Badge side={hero.home} />
                        <div className="text-center">
                            <div className="text-4xl font-black tabular-nums text-white sm:text-5xl">{hero.home.score} <span className="text-neutral-400">x</span> {hero.away.score}</div>
                            <div className="mt-1 text-xs text-neutral-300">{[hero.status, hero.penalties ? `Pênaltis ${hero.penalties}` : null, hero.sets].filter(Boolean).join(' · ')}</div>
                        </div>
                        <Badge side={hero.away} />
                    </div>
                ) : (
                    <div className="relative mt-6">
                        <h2 className="text-2xl font-black text-white sm:text-3xl">{document.subtitle}</h2>
                        <p className="mt-1 text-xs text-neutral-400">Gerado em {document.generated_at}</p>
                    </div>
                )}
                <ul className="relative mt-5 flex flex-wrap gap-2">
                    {document.meta.map((item) => (
                        <li key={item.label} className="flex max-w-full items-center gap-1.5 rounded-full bg-[#ffffff]/10 px-3 py-1 text-xs font-bold text-neutral-200" title={item.label}>
                            <ReportIcon path={icons[item.icon]} color={accent} size={13} /><span className="truncate">{item.value}</span>
                        </li>
                    ))}
                </ul>
                <span className="absolute inset-x-0 bottom-0 h-1" style={{ background: accent }} />
            </header>
            <div className="space-y-8 px-5 py-6 sm:px-8">
                {document.sections.map((section) => (
                    <section key={section.key} aria-labelledby={`report-${section.key}`}>
                        <div className="mb-4 flex items-center gap-3">
                            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full" style={{ background: accent }}><ReportIcon path={icons[section.icon]} color="#ffffff" size={16} /></span>
                            <h3 id={`report-${section.key}`} className="text-lg font-bold">{section.title}</h3>
                            <span className="h-px flex-1 bg-neutral-200" />
                        </div>
                        <div className="space-y-5">
                            {section.blocks.map((block, index) => (
                                <div key={index} className={block.type === 'table' || block.type === 'timeline' ? 'overflow-x-auto' : ''}>
                                    <div className={block.type === 'table' ? 'min-w-[32rem]' : ''}><ReportBlock block={block} icons={icons} accent={accent} tone="paper" /></div>
                                </div>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
            <footer className="flex flex-wrap justify-between gap-2 border-t border-neutral-200 px-5 py-3 text-[11px] text-neutral-500 sm:px-8">
                <span>{document.organization} · gerado em {document.generated_at}</span>
                {document.private && <span className="font-bold">USO INTERNO</span>}
            </footer>
        </article>
    );
}

import ReportBlock, { ReportIcon, ReportTone } from '@/Components/Report/ReportBlock';
import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { compactBlock, isWideBlock, msToNextSlide, reportSlideAt, ReportOverlayState, ReportStyle } from '@/lib/report';
import { Head } from '@inertiajs/react';
import { CSSProperties, useEffect, useState } from 'react';

const SHELL: Record<ReportStyle, { tone: ReportTone; className: string; style: (background: string) => CSSProperties }> = {
    broadcast: { tone: 'dark', className: 'rounded-3xl border border-white/10 shadow-2xl', style: (background) => ({ background: `${background}f2` }) },
    glass: { tone: 'dark', className: 'rounded-3xl border border-white/25 shadow-2xl', style: (background) => ({ background: `${background}99` }) },
    light: { tone: 'light', className: 'rounded-3xl shadow-2xl', style: () => ({ background: '#ffffff' }) },
};

/** O slide (seção) no ar agora, recalculado quando o automático vira a página — sem depender do servidor a cada troca. */
function useSlide(report: ReportOverlayState | null | undefined, skew: number): number {
    const [now, setNow] = useState(() => Date.now() - skew);

    useEffect(() => {
        if (!report || report.mode !== 'auto') return;

        let timer = 0;
        const schedule = () => {
            const current = Date.now() - skew;
            setNow(current);
            timer = window.setTimeout(schedule, (msToNextSlide(report, current) ?? 1000) + 30);
        };
        schedule();

        return () => window.clearTimeout(timer);
    }, [report?.mode, report?.seconds, report?.anchor_ms, report?.step, skew]); // eslint-disable-line react-hooks/exhaustive-deps

    return report ? reportSlideAt(report, report.count, report.mode === 'auto' ? now : 0) : 0;
}

/** Relatórios ilustrados no OBS: cada seção do relatório é um slide 1920 x 1080 que o operador passa (ou que passa sozinho). */
export default function Report({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
    const report = state.report;
    const slide = useSlide(report, skew);
    const visible = state.screens.report === true && report !== null && report !== undefined && report.count > 0;
    const appearance = state.appearance;

    if (!visible || !report) {
        return <div className="fixed inset-0" data-testid="overlay-report" data-visible="false"><Head title="Relatório" /></div>;
    }

    const document = report.document;
    const section = document.sections[slide] ?? document.sections[0];
    const shell = SHELL[report.style];
    const blocks = section.blocks.map(compactBlock);
    const many = blocks.filter((block) => !isWideBlock(block)).length > 1;
    const motion = appearance.motion === 'none' ? '' : `stats-in-${report.animation}`;
    const accent = document.accent;
    const hero = document.hero;

    return (
        <div className="fixed inset-0 flex items-center justify-center font-sans" style={overlayStyle(appearance)} data-testid="overlay-report" data-visible="true" data-slide={slide}>
            <Head title="Relatório" />
            <div style={{ transform: `scale(${(report.scale / 100) * appearance.scale * 1.45})`, transformOrigin: 'center' }}>
                <div key={`${section.key}-${slide}`} className={`w-[64rem] overflow-hidden ${shell.className} ${motion}`} style={shell.style(appearance.background_color)}>
                    <header className="relative flex items-center gap-4 overflow-hidden bg-[#0f172a] px-6 py-4 text-white">
                        <span className="pointer-events-none absolute -right-10 top-0 h-full w-56 skew-x-[-20deg] opacity-25" style={{ background: accent }} />
                        <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full" style={{ background: accent }}><ReportIcon path={document.icons[section.icon]} color="#ffffff" size={22} /></span>
                        <div className="relative min-w-0 flex-1">
                            <div className="truncate text-[11px] font-bold uppercase tracking-[0.2em] text-white/60">{document.title} · {document.subtitle}</div>
                            <div className="truncate text-2xl font-black uppercase tracking-wide">{section.title}</div>
                        </div>
                        {hero && (
                            <div className="relative flex shrink-0 items-center gap-3 rounded-full bg-white/10 px-4 py-1.5 text-lg font-black tabular-nums">
                                <span className="h-3 w-3 rounded-full" style={{ background: hero.home.color }} />{hero.home.short} {hero.home.score}
                                <span className="text-white/40">x</span>
                                {hero.away.score} {hero.away.short}<span className="h-3 w-3 rounded-full" style={{ background: hero.away.color }} />
                            </div>
                        )}
                    </header>
                    <div className="grid max-h-[34rem] grid-cols-2 gap-5 overflow-hidden p-6">
                        {blocks.map((block, index) => (
                            <div key={index} className={isWideBlock(block) || !many ? 'col-span-2' : ''}>
                                <ReportBlock block={block} icons={document.icons} accent={accent} tone={shell.tone} animate={appearance.motion !== 'none'} />
                            </div>
                        ))}
                    </div>
                    <footer className="flex items-center justify-between gap-4 px-6 pb-4" style={{ color: shell.tone === 'dark' ? 'rgba(255,255,255,0.6)' : '#64748b' }}>
                        <span className="truncate text-xs font-bold uppercase tracking-widest">{document.organization}</span>
                        <span className="flex items-center gap-1.5" aria-label={`Slide ${slide + 1} de ${report.count}`}>
                            {document.sections.map((item, index) => <span key={item.key} className="h-1.5 rounded-full transition-[opacity]" style={{ width: index === slide ? 28 : 10, background: index === slide ? accent : 'currentColor', opacity: index === slide ? 1 : 0.35 }} />)}
                        </span>
                    </footer>
                </div>
            </div>
        </div>
    );
}

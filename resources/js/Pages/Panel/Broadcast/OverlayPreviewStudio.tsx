import { Badge, Button } from '@/Components/Panel/ui';
import { OverlayPreviewLayers, overlayPreviewStatus } from '@/lib/overlayPreview';
import { useEffect, useRef, useState } from 'react';

interface OverlayLink {
    slug: string;
    title: string;
    url: string;
}

type Backdrop = 'field' | 'court' | 'dark' | 'grid';

const backdropOptions: { value: Backdrop; label: string }[] = [
    { value: 'field', label: 'Campo' },
    { value: 'court', label: 'Quadra' },
    { value: 'dark', label: 'Escuro' },
    { value: 'grid', label: 'Transparência' },
];

const toneClasses = {
    live: 'bg-emerald-100 text-emerald-800',
    automatic: 'bg-sky-100 text-sky-800',
    hidden: 'bg-amber-100 text-amber-800',
    waiting: 'bg-gray-100 text-gray-600',
};

function PreviewBackdrop({ backdrop }: { backdrop: Backdrop }) {
    if (backdrop === 'field') {
        return (
            <div className="absolute inset-0 bg-emerald-950">
                <div className="absolute inset-[8%] rounded-sm border border-white/25" />
                <div className="absolute inset-y-[8%] left-1/2 border-l border-white/20" />
                <div className="absolute left-1/2 top-1/2 h-[30%] aspect-square -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/20" />
                <div className="absolute inset-0 bg-gradient-to-br from-emerald-700/30 via-transparent to-black/30" />
            </div>
        );
    }

    if (backdrop === 'court') {
        return (
            <div className="absolute inset-0 bg-amber-800">
                <div className="absolute inset-[8%] border border-white/20" />
                <div className="absolute inset-y-[8%] left-1/2 border-l border-white/20" />
                <div className="absolute inset-0 bg-gradient-to-br from-orange-500/30 via-transparent to-stone-950/35" />
            </div>
        );
    }

    if (backdrop === 'grid') {
        return <div className="absolute inset-0 bg-white [background-image:linear-gradient(45deg,#e5e7eb_25%,transparent_25%),linear-gradient(-45deg,#e5e7eb_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#e5e7eb_75%),linear-gradient(-45deg,transparent_75%,#e5e7eb_75%)] [background-position:0_0,0_8px,8px_-8px,-8px_0] [background-size:16px_16px]" />;
    }

    return <div className="absolute inset-0 bg-slate-950" />;
}

function LivePreview({ overlay, backdrop }: { overlay: OverlayLink; backdrop: Backdrop }) {
    const frame = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(0);

    useEffect(() => {
        const element = frame.current;
        if (!element) return;

        const resize = () => setScale(element.clientWidth / 1920);
        resize();
        const observer = new ResizeObserver(resize);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return (
        <div ref={frame} className="relative aspect-video overflow-hidden bg-slate-950" aria-label={`Prévia ao vivo: ${overlay.title}`}>
            <PreviewBackdrop backdrop={backdrop} />
            <iframe
                key={overlay.url}
                src={overlay.url}
                title={`Prévia de ${overlay.title}`}
                width="1920"
                height="1080"
                tabIndex={-1}
                loading="lazy"
                className="pointer-events-none absolute left-0 top-0 border-0"
                style={{ transform: `scale(${scale})`, transformOrigin: 'top left', opacity: scale > 0 ? 1 : 0 }}
            />
            <div className="pointer-events-none absolute bottom-2 right-2 rounded bg-black/55 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-white/80">1920 × 1080</div>
        </div>
    );
}

function PreviewPlaceholder({ index }: { index: number }) {
    return (
        <div className="relative grid aspect-video place-items-center overflow-hidden bg-slate-950">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(16,185,129,0.18),transparent_38%),linear-gradient(135deg,transparent_45%,rgba(255,255,255,0.04)_45%,rgba(255,255,255,0.04)_55%,transparent_55%)]" />
            <div className="relative text-center">
                <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl border border-white/10 bg-white/5 text-lg font-black text-emerald-300">0{index + 1}</div>
                <p className="mt-3 text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Prévia em espera</p>
            </div>
        </div>
    );
}

async function copyUrl(url: string, onCopied: () => void) {
    try {
        await navigator.clipboard.writeText(url);
        onCopied();
    } catch {
        window.prompt('Copie a URL:', url);
    }
}

export default function OverlayPreviewStudio({ overlays, hasGame, layers }: { overlays: OverlayLink[]; hasGame: boolean; layers: OverlayPreviewLayers }) {
    const [activeSlug, setActiveSlug] = useState(overlays[0]?.slug ?? '');
    const [backdrop, setBackdrop] = useState<Backdrop>('field');
    const [copiedSlug, setCopiedSlug] = useState<string | null>(null);

    useEffect(() => {
        if (!overlays.some((overlay) => overlay.slug === activeSlug)) {
            setActiveSlug(overlays[0]?.slug ?? '');
        }
    }, [activeSlug, overlays]);

    const activeOverlay = overlays.find((overlay) => overlay.slug === activeSlug) ?? overlays[0];
    const activeStatus = activeOverlay ? overlayPreviewStatus(activeOverlay.slug, hasGame, layers) : null;

    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/70">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:px-5">
                <div>
                    <div className="flex items-center gap-2">
                        <h3 className="font-semibold text-slate-950">Estúdio de pré-visualização</h3>
                        <Badge>{overlays.length} telas</Badge>
                    </div>
                    <p className="mt-1 max-w-2xl text-xs leading-5 text-slate-500">A prévia usa a mesma URL do OBS. Para manter o painel leve, somente o card selecionado fica conectado ao vivo.</p>
                </div>
                <div className="flex flex-wrap items-center gap-1 rounded-lg bg-slate-100 p-1" aria-label="Fundo da prévia">
                    {backdropOptions.map((option) => (
                        <button
                            type="button"
                            key={option.value}
                            onClick={() => setBackdrop(option.value)}
                            aria-pressed={backdrop === option.value}
                            className={`rounded-md px-2.5 py-1.5 text-xs font-medium transition ${backdrop === option.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            {activeOverlay && activeStatus ? (
                <div className="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_15rem]">
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div className="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                            <div className="min-w-0">
                                <h4 className="truncate text-sm font-semibold text-slate-900">{activeOverlay.title}</h4>
                                <p className="mt-1 text-xs text-slate-500">{activeStatus.description}</p>
                            </div>
                            <span className={`shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${toneClasses[activeStatus.tone]}`}>{activeStatus.label}</span>
                        </div>
                        <LivePreview overlay={activeOverlay} backdrop={backdrop} />
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-3 py-3">
                            <span className="text-xs text-slate-500">Uma única conexão ao vivo é mantida para preservar recursos.</span>
                            <div className="flex items-center gap-2">
                                <Button small variant="ghost" onClick={() => copyUrl(activeOverlay.url, () => { setCopiedSlug(activeOverlay.slug); window.setTimeout(() => setCopiedSlug(null), 1500); })}>{copiedSlug === activeOverlay.slug ? 'Copiado!' : 'Copiar URL'}</Button>
                                <a href={activeOverlay.url} target="_blank" rel="noreferrer" className="inline-flex items-center rounded-md bg-white px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 transition hover:bg-slate-50">Abrir</a>
                            </div>
                        </div>
                    </div>
                    <nav aria-label="Telas do visualizador" className="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-2 lg:overflow-visible">
                        {overlays.map((overlay, index) => {
                            const status = overlayPreviewStatus(overlay.slug, hasGame, layers);
                            const active = overlay.slug === activeOverlay.slug;
                            return <button type="button" key={overlay.slug} onClick={() => setActiveSlug(overlay.slug)} aria-current={active ? 'page' : undefined} className={`flex min-w-[11rem] items-center gap-2 rounded-lg px-3 py-2 text-left text-xs transition lg:w-full ${active ? 'bg-emerald-50 text-emerald-900 ring-1 ring-emerald-400' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'}`}><span className="text-[10px] font-black tabular-nums text-slate-300">{String(index + 1).padStart(2, '0')}</span><span className="min-w-0 flex-1 truncate font-semibold">{overlay.title}</span><span className={`h-2 w-2 rounded-full ${status.tone === 'live' ? 'bg-emerald-500' : status.tone === 'hidden' ? 'bg-amber-400' : 'bg-slate-300'}`} /></button>;
                        })}
                    </nav>
                </div>
            ) : <PreviewPlaceholder index={0} />}
        </section>
    );
}

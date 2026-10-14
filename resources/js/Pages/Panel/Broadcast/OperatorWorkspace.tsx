import { COLS, Layout, moveTo, PANELS, PanelId, resizeTo, ROWS } from '@/lib/operatorLayout';
import { PointerEvent as ReactPointerEvent, KeyboardEvent as ReactKeyboardEvent, ReactNode, useEffect, useRef, useState } from 'react';

export type Block = { right?: ReactNode; body: ReactNode };

type Drag = { id: PanelId; mode: 'move' | 'resize'; base: Layout; startX: number; startY: number; cellW: number; cellH: number };

/** Largura mínima para a grade livre; abaixo disso os blocos empilham (celular/tablet) e o layout não é editável. */
const useWide = () => {
    const query = '(min-width: 1024px)';
    const [wide, setWide] = useState(() => (typeof window === 'undefined' ? true : window.matchMedia(query).matches));

    useEffect(() => {
        const media = window.matchMedia(query);
        const update = () => setWide(media.matches);
        media.addEventListener('change', update);

        return () => media.removeEventListener('change', update);
    }, []);

    return wide;
};

/**
 * Área de trabalho do Modo operador: blocos posicionados numa grade de COLS x ROWS que ocupa a tela toda (sem rolagem da página).
 * No modo de edição o título arrasta o bloco e o canto inferior direito redimensiona, com encaixe nas células; o conteúdo dos
 * blocos fica inerte para não acionar nada ao vivo por engano. Teclado: setas movem, Shift + setas redimensionam.
 */
export default function OperatorWorkspace({ layout, editing, onChange, blocks }: { layout: Layout; editing: boolean; onChange: (next: Layout) => void; blocks: Partial<Record<PanelId, Block>> }) {
    const wide = useWide();
    const area = useRef<HTMLDivElement>(null);
    const drag = useRef<Drag | null>(null);
    const [preview, setPreview] = useState<Layout | null>(null);
    const [active, setActive] = useState<PanelId | null>(null);
    const [target, setTarget] = useState<{ x: number; y: number; w: number; h: number; ok: boolean } | null>(null);
    const shown = (preview ?? layout).filter((item) => blocks[item.id]);

    const begin = (event: ReactPointerEvent, id: PanelId, mode: Drag['mode']) => {
        if (!editing || !area.current || event.button !== 0) return;

        const rect = area.current.getBoundingClientRect();
        drag.current = { id, mode, base: layout, startX: event.clientX, startY: event.clientY, cellW: rect.width / COLS, cellH: rect.height / ROWS };
        event.currentTarget.setPointerCapture(event.pointerId);
        setActive(id);
        event.preventDefault();
    };
    const move = (event: ReactPointerEvent) => {
        const current = drag.current;

        if (!current) return;

        const item = current.base.find((entry) => entry.id === current.id)!;
        const dx = Math.round((event.clientX - current.startX) / current.cellW);
        const dy = Math.round((event.clientY - current.startY) / current.cellH);
        const next = current.mode === 'move' ? moveTo(current.base, current.id, item.x + dx, item.y + dy) : resizeTo(current.base, current.id, item.w + dx, item.h + dy);

        // Posição que não cabe: fica na última válida e o contorno-alvo fica vermelho.
        if (next) setPreview(next);

        const goal = current.mode === 'move' ? { ...item, x: item.x + dx, y: item.y + dy } : { ...item, w: item.w + dx, h: item.h + dy };
        setTarget({ x: Math.max(0, goal.x), y: Math.max(0, goal.y), w: Math.max(1, goal.w), h: Math.max(1, goal.h), ok: next !== null });
    };
    const end = () => {
        if (drag.current && preview) onChange(preview);
        drag.current = null;
        setPreview(null);
        setActive(null);
        setTarget(null);
    };
    const key = (event: ReactKeyboardEvent, id: PanelId) => {
        const delta: Record<string, [number, number]> = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
        const step = delta[event.key];

        if (!step) return;

        // As setas aqui são do layout: não podem passar a escalação nem acionar atalhos da transmissão.
        event.preventDefault();
        event.stopPropagation();

        const item = layout.find((entry) => entry.id === id)!;
        const next = event.shiftKey ? resizeTo(layout, id, item.w + step[0], item.h + step[1]) : moveTo(layout, id, item.x + step[0], item.y + step[1]);

        if (next) onChange(next);
    };

    const ordered = wide ? shown : [...shown].sort((a, b) => a.y - b.y || a.x - b.x);

    return (
        <div ref={area} className={wide ? 'relative min-h-0 flex-1' : 'flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto'} data-testid="operator-grid" data-editing={editing && wide ? 'true' : 'false'}>
            {editing && wide && <div className="pointer-events-none absolute inset-0 rounded-lg opacity-60" style={{ backgroundImage: 'linear-gradient(to right, rgba(13,148,136,.15) 1px, transparent 1px), linear-gradient(to bottom, rgba(13,148,136,.15) 1px, transparent 1px)', backgroundSize: `calc(100% / ${COLS}) calc(100% / ${ROWS})` }} aria-hidden="true" />}
            {target && wide && <div className={`pointer-events-none absolute z-20 rounded-lg border-2 border-dashed ${target.ok ? 'border-teal-500 bg-teal-500/10' : 'border-red-500 bg-red-500/10'}`} style={{ left: `calc(${(target.x / COLS) * 100}% + 3px)`, top: `calc(${(target.y / ROWS) * 100}% + 3px)`, width: `calc(${(target.w / COLS) * 100}% - 6px)`, height: `calc(${(target.h / ROWS) * 100}% - 6px)` }} data-testid="operator-target" aria-hidden="true" />}
            {ordered.map((item) => {
                const meta = PANELS.find((panel) => panel.id === item.id)!;
                const block = blocks[item.id]!;
                const dragging = active === item.id;
                const style = wide ? { left: `${(item.x / COLS) * 100}%`, top: `${(item.y / ROWS) * 100}%`, width: `${(item.w / COLS) * 100}%`, height: `${(item.h / ROWS) * 100}%` } : undefined;

                return (
                    <div key={item.id} style={style} data-panel={item.id} data-cell={`${item.x},${item.y},${item.w},${item.h}`} className={wide ? `absolute p-[3px] ${dragging ? 'z-10' : 'transition-[left,top,width,height] duration-100'}` : 'shrink-0'}>
                        <section aria-label={meta.title} className={`relative flex h-full min-h-0 flex-col rounded-lg border bg-white p-2 ${editing && wide ? (dragging ? 'border-teal-600 shadow-lg ring-2 ring-teal-500' : 'border-dashed border-teal-500') : 'border-gray-200'}`}>
                            <div className="mb-1.5 flex shrink-0 items-center justify-between gap-2">
                                {editing && wide ? (
                                    <button type="button" aria-label={`Mover ${meta.title} (setas movem, Shift e setas redimensionam)`} onPointerDown={(event) => begin(event, item.id, 'move')} onPointerMove={move} onPointerUp={end} onPointerCancel={end} onKeyDown={(event) => key(event, item.id)} className="-mx-1 flex min-w-0 flex-1 cursor-move touch-none items-center gap-1 rounded px-1 text-left hover:bg-teal-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                        <span aria-hidden="true" className="text-gray-400">⠿</span>
                                        <h3 className="truncate text-[11px] font-bold uppercase tracking-wide text-gray-600">{meta.title}</h3>
                                    </button>
                                ) : (
                                    <h3 className="truncate text-[11px] font-bold uppercase tracking-wide text-gray-500">{meta.title}</h3>
                                )}
                                {!(editing && wide) && block.right}
                                {editing && wide && <span className="shrink-0 text-[10px] font-semibold tabular-nums text-teal-700">{item.w}×{item.h}</span>}
                            </div>
                            <div className={`min-h-0 flex-1 overflow-y-auto overflow-x-hidden ${editing && wide ? 'pointer-events-none select-none opacity-70' : ''}`} {...(editing && wide ? ({ inert: '' } as object) : {})}>{block.body}</div>
                            {editing && wide && (
                                <button type="button" aria-label={`Redimensionar ${meta.title}`} onPointerDown={(event) => begin(event, item.id, 'resize')} onPointerMove={move} onPointerUp={end} onPointerCancel={end} onKeyDown={(event) => key(event, item.id)} className="absolute bottom-0 right-0 h-5 w-5 cursor-nwse-resize touch-none rounded-tl-lg rounded-br-lg bg-teal-500/80 text-[10px] leading-none text-white hover:bg-teal-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-700">
                                    <span aria-hidden="true">◢</span>
                                </button>
                            )}
                        </section>
                    </div>
                );
            })}
        </div>
    );
}

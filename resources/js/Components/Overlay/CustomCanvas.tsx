import { animationFrames, BuilderElement, BuilderItem, DataContext, elementBox, exitAnimation, resolveImage, resolveText, textStyle, totalDuration } from '@/lib/customBuilder';
import { overlayFontFamily } from '@/lib/overlayVisual';
import { CSSProperties, useEffect, useRef, useState } from 'react';

interface Props {
    item: Pick<BuilderItem, 'width' | 'height' | 'elements'>;
    ctx: DataContext;
    /** No ar? (entra com a animação de cada elemento; ao sair toca ao contrário) */
    live: boolean;
    /** Muda para reproduzir a entrada de novo (editor). */
    replayKey?: number;
    /** Canal sem animação: entra e sai na hora. */
    instant?: boolean;
    /** Fonte global do canal (usada pelos textos com fonte "global"). */
    fontFamily?: string;
    /** Editor: elemento selecionado (moldura) e clique/arraste. */
    selectedId?: string | null;
    onPointerDown?: (id: string, event: React.PointerEvent) => void;
}

/** Canvas do overlay livre: o mesmo desenho no OBS e na prévia do painel. */
export default function CustomCanvas({ item, ctx, live, replayKey = 0, instant = false, fontFamily, selectedId = null, onPointerDown }: Props) {
    const nodes = useRef<Record<string, HTMLDivElement | null>>({});
    const first = useRef(true);
    const [hidden, setHidden] = useState(!live);
    const elements = item.elements.filter((element) => element.visible);

    useEffect(() => {
        const wasFirst = first.current;
        first.current = false;
        const timers: number[] = [];

        if (live) {
            setHidden(false);
            requestAnimationFrame(() => play(elements, nodes.current, 'in', instant));
        } else if (wasFirst) {
            setHidden(true);
        } else {
            play(elements, nodes.current, 'out', instant);
            timers.push(window.setTimeout(() => setHidden(true), instant ? 0 : totalDuration(elements) + 50));
        }

        return () => timers.forEach((timer) => window.clearTimeout(timer));
    }, [live, replayKey]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div style={{ position: 'relative', width: item.width, height: item.height, visibility: hidden ? 'hidden' : 'visible', overflow: 'hidden', fontFamily }} data-testid="custom-canvas" data-live={live}>
            {elements.map((element) => (
                <div key={element.id} ref={(node) => { nodes.current[element.id] = node; }} data-element={element.id} style={{ ...elementBox(element), ...(onPointerDown && !element.locked ? { cursor: 'move' } : {}), outline: selectedId === element.id ? '2px dashed #14b8a6' : undefined, outlineOffset: 2 }} onPointerDown={onPointerDown ? (event) => onPointerDown(element.id, event) : undefined}>
                    <ElementBody element={element} ctx={ctx} fontFamily={fontFamily} />
                </div>
            ))}
        </div>
    );
}

function ElementBody({ element, ctx, fontFamily }: { element: BuilderElement; ctx: DataContext; fontFamily: string | undefined }) {
    if (element.type === 'text') {
        const family = element.font === 'global' ? fontFamily : overlayFontFamily(element.font);
        const content = resolveText(element.text, ctx);
        const style: CSSProperties = textStyle(element, family);

        return (
            <div style={style}>
                {element.marquee ? <span style={{ display: 'inline-block', whiteSpace: 'nowrap', paddingLeft: '100%', animation: `custom-marquee ${Math.max(2, element.marqueeSpeed)}s linear infinite` }}>{content}</span> : content}
            </div>
        );
    }

    if (element.type === 'image') {
        const src = resolveImage(element.src, ctx);

        return src ? <img src={src} alt="" draggable={false} style={{ width: '100%', height: '100%', objectFit: element.fit, borderRadius: element.shape === 'circle' ? '50%' : undefined }} /> : null;
    }

    return null;
}

function play(elements: BuilderElement[], nodes: Record<string, HTMLDivElement | null>, direction: 'in' | 'out', instant: boolean): void {
    for (const element of elements) {
        const node = nodes[element.id];
        const animation = direction === 'in' ? element.animIn : exitAnimation(element);
        const frames = animationFrames(animation);
        if (!node || typeof node.animate !== 'function') continue;

        node.animate(frames.length === 0 ? [{ opacity: direction === 'in' ? 1 : 0 }, { opacity: direction === 'in' ? 1 : 0 }] : frames, {
            duration: instant ? 1 : element.duration, delay: instant ? 0 : element.delay, easing: animation === 'pop' ? 'ease-out' : 'cubic-bezier(.16,1,.3,1)', fill: 'both', direction: direction === 'in' ? 'normal' : 'reverse',
        });
    }
}

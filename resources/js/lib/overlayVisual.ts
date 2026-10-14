import { CSSProperties } from 'react';
import { OverlayAppearance } from './overlay';

export function overlayPosition(position: OverlayAppearance['position']): string {
    return {
        'top-left': 'items-start justify-start',
        'top-center': 'items-start justify-center',
        'top-right': 'items-start justify-end',
        'bottom-left': 'items-end justify-start',
        'bottom-center': 'items-end justify-center',
        'bottom-right': 'items-end justify-end',
    }[position];
}

/** Ponto de origem do `scale`: o placar cresce a partir do canto/borda em que está ancorado. */
export function overlayOrigin(position: OverlayAppearance['position']): string {
    return position.replace('-', ' ');
}

/** Cor `#rrggbb` com transparência: `alpha` (0 a 1) do estilo vezes a opacidade escolhida (`surface`, em %). Opaco de verdade devolve a cor pura. */
export function withAlpha(hex: string, alpha: number, surface = 100): string {
    const value = Math.max(0, Math.min(1, alpha * (surface / 100)));

    return value >= 1 ? hex : `${hex}${Math.round(value * 255).toString(16).padStart(2, '0')}`;
}

const SHADOW_CLASS: Record<string, string> = { none: '', soft: 'shadow-lg', strong: 'shadow-2xl' };

/** Posição livre (x e y em % da tela) quando os dois estão definidos; senão vale a posição nomeada. */
export function freePosition(appearance: Pick<OverlayAppearance, 'x' | 'y'>): { left: string; top: string } | null {
    return typeof appearance.x === 'number' && typeof appearance.y === 'number' ? { left: `${appearance.x}%`, top: `${appearance.y}%` } : null;
}

/** Aparência de cada estilo (moldura do placar). O contrato de dados é o mesmo; só muda a apresentação. */
export function scoreboardFrame(appearance: OverlayAppearance): { className: string; style: CSSProperties } {
    const { preset, background_color: bg, accent_color: accent, layout } = appearance;
    const surface = appearance.surface ?? 100;
    const shadow = SHADOW_CLASS[appearance.shadow ?? 'soft'] ?? SHADOW_CLASS.soft;
    const rounded = layout === 'compact' ? 'rounded-full' : layout === 'wide' ? 'rounded-none' : layout === 'stacked' ? 'rounded-xl' : null;
    const display = layout === 'wide' ? 'flex w-[1840px] max-w-full' : 'inline-flex';
    const base = `relative ${display} items-stretch overflow-hidden`;
    const custom: CSSProperties = typeof appearance.radius === 'number' ? { borderRadius: `${appearance.radius}px` } : {};
    const round = (fallback: string) => (typeof appearance.radius === 'number' ? '' : rounded ?? fallback);
    const lift = (fallback: string) => (shadow === '' ? '' : shadow === 'shadow-2xl' ? 'shadow-2xl' : fallback);

    switch (preset) {
        case 'classic':
            return { className: `${base} ${round('rounded-md')} ${shadow}`, style: { backgroundColor: withAlpha(bg, 1, surface), ...custom } };
        case 'minimal':
            return { className: `${base} ${round('rounded-full')} border border-white/10 ${shadow}`, style: { backgroundColor: withAlpha(bg, 0.7, surface), ...custom } };
        case 'glass':
            return { className: `${base} ${round('rounded-2xl')} border border-white/25 backdrop-blur-md ${shadow}`, style: { backgroundColor: withAlpha(bg, 0.4, surface), ...custom } };
        case 'contrast':
            return { className: `${base} ${round('rounded-lg')} border-2 ${shadow}`, style: { backgroundColor: withAlpha(bg, 1, surface), borderColor: accent, ...custom } };
        case 'neon':
            return {
                className: `${base} ${round('rounded-xl')} border-2`,
                style: { backgroundColor: withAlpha(bg, 0.95, surface), borderColor: accent, boxShadow: shadow === '' ? 'none' : `0 0 ${shadow === 'shadow-2xl' ? 28 : 16}px ${accent}aa`, ...custom },
            };
        case 'ribbon':
            return {
                className: `${base} ${round('rounded-none')} ${shadow}`,
                style: { background: `linear-gradient(90deg, ${withAlpha(bg, 1, surface)}, ${withAlpha(bg, 0.9, surface)} 70%, ${withAlpha(accent, 0.4, surface)})`, clipPath: 'polygon(0 0, 100% 0, 98% 100%, 0 100%)', ...custom },
            };
        case 'gradient':
            return { className: `${base} ${round('rounded-2xl')} ${shadow}`, style: { background: `linear-gradient(120deg, ${withAlpha(accent, 1, surface)}, ${withAlpha(bg, 1, surface)} 60%)`, ...custom } };
        default:
            return {
                className: `${base} ${round('rounded-2xl')} border border-white/15 ${lift('shadow-2xl')}`,
                style: { background: `linear-gradient(135deg, ${withAlpha(bg, 0.95, surface)}, ${withAlpha(bg, 0.85, surface)})`, ...custom },
            };
    }
}

export function overlayMotion(motion: OverlayAppearance['motion']): string {
    return motion === 'dynamic' ? 'overlay-motion-dynamic' : motion === 'subtle' ? 'overlay-motion-subtle' : '';
}

const FONT_STACKS: Record<string, string> = {
    inter: '"Inter","Segoe UI",Arial,sans-serif',
    montserrat: '"Montserrat","Segoe UI",Arial,sans-serif',
    oswald: '"Oswald","Arial Narrow",Impact,sans-serif',
    bebas: '"Bebas Neue",Impact,"Arial Narrow",sans-serif',
    anton: '"Anton",Impact,"Arial Narrow",sans-serif',
    barlow: '"Barlow Condensed","Arial Narrow",Arial,sans-serif',
    'roboto-condensed': '"Roboto Condensed","Arial Narrow",Arial,sans-serif',
    archivo: '"Archivo Black","Arial Black",Impact,sans-serif',
    playfair: '"Playfair Display",Georgia,"Times New Roman",serif',
    marker: '"Permanent Marker","Comic Sans MS",cursive',
};

export const OVERLAY_FONT_KEYS = Object.keys(FONT_STACKS);

/** Pilha de fontes da escolha do canal; `undefined` = fonte padrão do sistema. Fonte própria (ID) usa a família registrada. */
export function overlayFontFamily(font: string): string | undefined {
    if (font === 'system' || font === '') return undefined;

    return FONT_STACKS[font] ?? '"OverlayCustom","Segoe UI",Arial,sans-serif';
}

export function overlayStyle(appearance: OverlayAppearance): CSSProperties {
    return {
        fontFamily: overlayFontFamily(appearance.font),
        '--overlay-accent': appearance.accent_color,
        '--overlay-background': appearance.background_color,
        '--overlay-text': appearance.text_color,
    } as CSSProperties;
}


/** Próximo patrocinador da rotação (volta ao primeiro; lista vazia ou que encolheu não quebra). */
export function nextSponsorIndex(current: number, count: number): number {
    return count <= 0 ? 0 : (current + 1) % count;
}

/** Moldura do cartão de patrocínio por preset: fundo claro sempre, para qualquer logotipo ficar legível. */
export function sponsorFrameClass(preset: OverlayAppearance['preset']): string {
    return { classic: 'rounded-md', minimal: 'rounded-full', contrast: 'rounded-lg', neon: 'rounded-xl', ribbon: 'rounded-none' }[preset as string] ?? 'rounded-2xl';
}

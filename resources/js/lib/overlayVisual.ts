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

/** Aparência de cada preset (moldura do placar). O contrato de dados é o mesmo; só muda a apresentação. */
export function scoreboardFrame(appearance: OverlayAppearance): { className: string; style: CSSProperties } {
    const { preset, background_color: bg } = appearance;

    if (preset === 'classic') {
        return { className: 'relative inline-flex items-stretch overflow-hidden rounded-md shadow-xl', style: { backgroundColor: bg } };
    }

    if (preset === 'minimal') {
        return { className: 'relative inline-flex items-stretch overflow-hidden rounded-full border border-white/10 shadow-lg', style: { backgroundColor: `${bg}b3` } };
    }

    return { className: 'relative inline-flex items-stretch overflow-hidden rounded-2xl border border-white/15 shadow-2xl', style: { background: `linear-gradient(135deg, ${bg}f2, ${bg}d9)` } };
}

export function overlayMotion(motion: OverlayAppearance['motion']): string {
    return motion === 'dynamic' ? 'overlay-motion-dynamic' : motion === 'subtle' ? 'overlay-motion-subtle' : '';
}

export function overlayStyle(appearance: OverlayAppearance): CSSProperties {
    return {
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
    return preset === 'classic' ? 'rounded-md' : preset === 'minimal' ? 'rounded-full' : 'rounded-2xl';
}

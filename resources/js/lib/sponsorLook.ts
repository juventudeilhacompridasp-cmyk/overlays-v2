/**
 * Aparência dos patrocinadores no OBS (código puro, testado em Node): rotação de logotipos (formato, estilo, animação)
 * e barra larga de 1500 x 200 (moldura, transição, ajuste da arte).
 */
import type { CSSProperties } from 'react';

export type SponsorFormat = 'logo' | 'logo_name' | 'text' | 'banner' | 'banner_name';
export type SponsorStyle = 'boxed' | 'clean' | 'ribbon';
export type SponsorAnimation = 'fade' | 'slide' | 'zoom' | 'rise' | 'flip';
export type BarTransition = 'fade' | 'slide' | 'zoom' | 'flip' | 'elastic';

export interface SponsorItem {
    name: string;
    logo_url: string;
    banner_url?: string | null;
}

export interface SponsorLook {
    format: SponsorFormat;
    style: SponsorStyle;
    animation: SponsorAnimation;
    animation_speed: number;
    scale: number;
    text_scale: number;
    x: number | null;
    y: number | null;
}

export interface BarLook {
    bar_seconds: number;
    bar_transition: BarTransition;
    bar_fit: 'cover' | 'contain';
    bar_border: 'none' | 'thin' | 'accent';
    bar_shadow: 'none' | 'soft' | 'strong';
    bar_scale: number;
    bar_x: number;
    bar_y: number;
    bar_opacity: number;
    bar_radius: number;
    bar_background: string;
}

export const SPONSOR_FORMATS: { key: SponsorFormat; label: string }[] = [
    { key: 'logo', label: 'Só o logotipo' },
    { key: 'logo_name', label: 'Logotipo e nome' },
    { key: 'text', label: 'Só o nome' },
    { key: 'banner', label: 'Banner largo' },
    { key: 'banner_name', label: 'Banner e nome' },
];

export const SPONSOR_STYLES: { key: SponsorStyle; label: string }[] = [
    { key: 'boxed', label: 'Caixa' },
    { key: 'clean', label: 'Limpo' },
    { key: 'ribbon', label: 'Faixa' },
];

export const SPONSOR_ANIMATIONS: { key: SponsorAnimation; label: string }[] = [
    { key: 'fade', label: 'Fade' },
    { key: 'slide', label: 'Deslize' },
    { key: 'zoom', label: 'Zoom' },
    { key: 'rise', label: 'Subida' },
    { key: 'flip', label: 'Virada 3D' },
];

export const BAR_TRANSITIONS: { key: BarTransition; label: string }[] = [
    { key: 'fade', label: 'Fade suave' },
    { key: 'slide', label: 'Deslizamento' },
    { key: 'zoom', label: 'Zoom elegante' },
    { key: 'flip', label: 'Virada 3D' },
    { key: 'elastic', label: 'Elástico' },
];

/** O que mostrar de um patrocinador no formato escolhido. O banner largo cai no logotipo se o patrocinador não enviou um. */
export function sponsorContent(item: SponsorItem, format: SponsorFormat): { image: string | null; wide: boolean; name: string | null } {
    const wide = format === 'banner' || format === 'banner_name';
    const image = format === 'text' ? null : wide ? item.banner_url ?? item.logo_url : item.logo_url;

    return { image, wide: wide && Boolean(item.banner_url), name: format === 'text' || format === 'logo_name' || format === 'banner_name' ? item.name : null };
}

/** Classes da moldura do cartão de patrocínio por estilo. */
export function sponsorFrame(style: SponsorStyle): string {
    return { boxed: 'bg-white/95 px-6 py-3 shadow-xl rounded-xl', clean: 'px-2 py-1', ribbon: 'bg-white/95 px-8 py-2 shadow-lg rounded-none border-l-8' }[style];
}

/** Duração (ms) da animação a partir da velocidade em %. */
export function animationMs(speed: number, base = 600): number {
    return Math.round(base / (Math.max(50, Math.min(200, speed)) / 100));
}

/** Próximo item da barra: volta ao primeiro e aguenta lista vazia ou que encolheu. */
export function barItems(items: SponsorItem[]): SponsorItem[] {
    const wide = items.filter((item) => item.banner_url);

    return wide.length > 0 ? wide : items;
}

/** Moldura (borda, sombra, raio, fundo, opacidade) da barra de 1500 x 200. */
export function barFrame(look: BarLook, accent: string): CSSProperties {
    const border = look.bar_border === 'none' ? 'none' : look.bar_border === 'accent' ? `4px solid ${accent}` : '2px solid rgba(255,255,255,.35)';
    const shadow = look.bar_shadow === 'none' ? 'none' : look.bar_shadow === 'soft' ? '0 6px 18px rgba(0,0,0,.35)' : '0 12px 34px rgba(0,0,0,.65)';

    return { width: 1500, height: 200, border, boxShadow: shadow, borderRadius: `${look.bar_radius}px`, backgroundColor: look.bar_background, opacity: look.bar_opacity / 100, overflow: 'hidden' };
}

/**
 * Conceitos visuais da escalação por equipe (código puro, testado em Node): cada conceito devolve as classes e estilos
 * de que a grade de cartões e o cartão individual precisam; os componentes só aplicam.
 */
import type { CSSProperties } from 'react';

export type LineupLayout = 'classic' | 'tunnel' | 'poster' | 'sidebar' | 'stadium' | 'matchday';
export type LineupStyle = 'editorial' | 'cards' | 'glass' | 'premier' | 'champions' | 'diagonal';
export type LineupAnimation = 'slide' | 'wipe' | 'rise' | 'zoom' | 'split' | 'cascade';

export const LINEUP_LAYOUTS: { key: LineupLayout; label: string; hint: string }[] = [
    { key: 'classic', label: 'Clássico', hint: 'Grade de cartões com estilo e animação à escolha' },
    { key: 'tunnel', label: 'Túnel de entrada', hint: 'Colunas altas, nomes na vertical; sobem em sequência' },
    { key: 'poster', label: 'Carta de coleção', hint: 'Cartas com moldura de foil que viram uma a uma' },
    { key: 'sidebar', label: 'Coluna lateral', hint: 'Lista compacta à esquerda que deixa o jogo livre' },
    { key: 'stadium', label: 'Telão de estádio', hint: 'Painel LED escuro com números luminosos' },
    { key: 'matchday', label: 'Programa do jogo', hint: 'Papel claro e tipografia de jornal' },
];

export const LINEUP_STYLES: { key: LineupStyle; label: string; hint: string }[] = [
    { key: 'editorial', label: 'Editorial', hint: 'Faixa da equipe, TV aberta' },
    { key: 'cards', label: 'Cards', hint: 'Cartas com cantos suaves' },
    { key: 'glass', label: 'Vidro premium', hint: 'Painel translúcido' },
    { key: 'premier', label: 'Liga principal', hint: 'Blocos de cor e placa branca' },
    { key: 'champions', label: 'Noite de gala', hint: 'Azul profundo e filete dourado' },
    { key: 'diagonal', label: 'Diagonal esportivo', hint: 'Cards em paralelogramo' },
];

export const LINEUP_ANIMATIONS: { key: LineupAnimation; label: string }[] = [
    { key: 'slide', label: 'Deslize' },
    { key: 'wipe', label: 'Cortina' },
    { key: 'rise', label: 'Subida' },
    { key: 'zoom', label: 'Zoom de estúdio' },
    { key: 'split', label: 'Abertura central' },
    { key: 'cascade', label: 'Cascata' },
];

export interface LineupLook {
    layout: LineupLayout;
    style: LineupStyle;
    animation: LineupAnimation;
    surface: number;
    radius: number | null;
    text_scale: number;
}

export interface Skin {
    /** Moldura da apresentação (painel inteiro). */
    panel: { className: string; style: CSSProperties };
    /** Colunas da grade de cartões (classes do Tailwind). */
    grid: string;
    /** Cada cartão de atleta. */
    card: { className: string; style: (color: string) => CSSProperties };
    /** Número da camisa. */
    number: { className: string; style: (color: string) => CSSProperties };
    /** Nome do atleta. */
    name: string;
    /** Proporção da foto do cartão. */
    photo: string;
    /** Conceitos com nome na vertical (túnel). */
    vertical: boolean;
    /** Lista compacta em vez de grade (coluna lateral). */
    list: boolean;
    /** Cor do texto forçada (papel claro). */
    ink: string | null;
    /** Largura da apresentação. */
    width: string;
}

/** Cor `#rrggbb` com opacidade em %. */
export function fade(hex: string, percent: number): string {
    const value = Math.max(0, Math.min(100, percent));

    return value >= 100 ? hex : `${hex}${Math.round((value / 100) * 255).toString(16).padStart(2, '0')}`;
}

export function lineupSkin(look: LineupLook, background: string, accent: string): Skin {
    const radius: CSSProperties = typeof look.radius === 'number' ? { borderRadius: `${look.radius}px` } : {};
    const base = { vertical: false, list: false, ink: null as string | null, photo: 'aspect-[4/5]', grid: 'grid-cols-6', width: 'w-[68rem]' };
    const number = { className: 'absolute bottom-1 left-1 px-2 py-0.5 text-2xl font-black tabular-nums', style: (color: string): CSSProperties => ({ backgroundColor: color }) };
    const name = 'block truncate text-lg font-black uppercase';

    switch (look.layout) {
        case 'tunnel':
            return {
                ...base, vertical: true, grid: 'grid-cols-11', photo: 'aspect-[2/5]', name: 'block max-h-48 truncate text-base font-black uppercase [writing-mode:vertical-rl] rotate-180',
                panel: { className: 'overflow-hidden rounded-none border-y-4 shadow-2xl', style: { background: fade(background, look.surface), borderColor: accent, ...radius } },
                card: { className: 'overflow-hidden', style: (color) => ({ background: `linear-gradient(180deg, ${color}aa, ${background})` }) },
                number,
            };
        case 'poster':
            return {
                ...base, grid: 'grid-cols-5', photo: 'aspect-[3/4]', name,
                panel: { className: 'overflow-hidden rounded-xl border border-white/10 shadow-2xl', style: { background: fade(background, look.surface), ...radius } },
                card: { className: 'overflow-hidden rounded-xl border-2 shadow-xl lineup-foil', style: (color) => ({ borderColor: '#facc15', background: `linear-gradient(145deg, ${color}66, ${background} 60%, ${color}33)` }) },
                number: { className: 'absolute right-1 top-1 rounded-full px-2.5 py-1 text-2xl font-black tabular-nums text-slate-900', style: () => ({ backgroundColor: '#facc15' }) },
            };
        case 'sidebar':
            return {
                ...base, list: true, grid: 'grid-cols-1', width: 'w-[26rem]', name: 'block truncate text-xl font-black uppercase',
                panel: { className: 'overflow-hidden rounded-r-2xl border-r-4 shadow-2xl', style: { background: fade(background, look.surface), borderColor: accent, ...radius } },
                card: { className: 'flex items-center gap-3 border-b border-white/10 px-4 py-2', style: () => ({}) },
                number: { className: 'grid h-10 w-12 place-items-center text-2xl font-black tabular-nums', style: (color) => ({ backgroundColor: color }) },
            };
        case 'stadium':
            return {
                ...base, grid: 'grid-cols-4', photo: 'aspect-[4/3]', name: 'block truncate text-xl font-black uppercase tracking-widest text-amber-300',
                panel: { className: 'overflow-hidden rounded-md border-4 border-neutral-800 shadow-2xl', style: { background: fade('#050505', look.surface), ...radius } },
                card: { className: 'overflow-hidden rounded-sm border border-amber-400/30', style: () => ({ background: '#0b0b0b' }) },
                number: { className: 'absolute bottom-1 left-1 px-2 text-4xl font-black tabular-nums text-amber-300', style: () => ({ textShadow: '0 0 14px rgba(252,211,77,.9)' }) },
            };
        case 'matchday':
            return {
                ...base, ink: '#1c1917', grid: 'grid-cols-5', photo: 'aspect-[4/5]', name: 'block truncate font-serif text-lg font-bold uppercase',
                panel: { className: 'overflow-hidden rounded-sm border border-stone-400 font-serif shadow-2xl', style: { background: fade('#f5efe0', look.surface), color: '#1c1917', ...radius } },
                card: { className: 'overflow-hidden border border-stone-400', style: () => ({ background: '#fffaf0' }) },
                number: { className: 'absolute bottom-1 left-1 px-2 py-0.5 font-serif text-2xl font-black tabular-nums text-white', style: () => ({ backgroundColor: '#7f1d1d' }) },
            };
        default:
            break;
    }

    // Conceito clássico: o estilo escolhe a pele.
    switch (look.style) {
        case 'cards':
            return { ...base, name, panel: { className: 'overflow-hidden rounded-3xl border border-white/10 shadow-2xl', style: { background: fade(background, look.surface), ...radius } }, card: { className: 'overflow-hidden rounded-2xl border border-white/10', style: (color) => ({ background: `linear-gradient(180deg, ${color}44, ${background})` }) }, number: { ...number, className: `${number.className} rounded-md` } };
        case 'glass':
            return { ...base, name, panel: { className: 'overflow-hidden rounded-2xl border border-white/25 shadow-2xl backdrop-blur-md', style: { background: fade(background, Math.min(look.surface, 60)), ...radius } }, card: { className: 'overflow-hidden rounded-xl border border-white/20', style: () => ({ background: 'rgba(255,255,255,.08)' }) }, number };
        case 'premier':
            return { ...base, name, panel: { className: 'overflow-hidden rounded-none shadow-2xl', style: { background: fade(background, look.surface), ...radius } }, card: { className: 'overflow-hidden rounded-none', style: (color) => ({ background: color }) }, number: { className: 'absolute bottom-0 left-0 bg-white px-2 py-0.5 text-2xl font-black tabular-nums text-slate-900', style: () => ({}) } };
        case 'champions':
            return { ...base, name, panel: { className: 'overflow-hidden rounded-lg border-2 shadow-2xl', style: { background: fade('#0a1a4a', look.surface), borderColor: '#d4af37', ...radius } }, card: { className: 'overflow-hidden rounded-md border', style: () => ({ borderColor: '#d4af3799', background: 'linear-gradient(180deg,#14307a,#0a1a4a)' }) }, number: { className: 'absolute inset-x-0 bottom-0 bg-black/50 py-0.5 text-center text-2xl font-black tabular-nums text-amber-300', style: () => ({}) } };
        case 'diagonal':
            return { ...base, name, panel: { className: 'overflow-hidden rounded-xl border border-white/10 shadow-2xl', style: { background: fade(background, look.surface), ...radius } }, card: { className: 'overflow-hidden -skew-x-6', style: (color) => ({ background: `linear-gradient(180deg, ${color}55, ${background})` }) }, number };
        default:
            return { ...base, name, panel: { className: 'overflow-hidden rounded-xl border border-white/10 shadow-2xl', style: { background: fade(background, look.surface), ...radius } }, card: { className: 'overflow-hidden rounded-lg border border-white/10', style: (color) => ({ background: `linear-gradient(180deg, ${color}44, ${background})` }) }, number };
    }
}

/** Classe CSS e atraso (ms) de entrada de cada elemento: só a cascata escalona. */
export function enterMotion(animation: LineupAnimation, index: number): { className: string; delay: number } {
    return { className: `lineup-enter-${animation}`, delay: animation === 'cascade' ? Math.min(index, 20) * 70 : 0 };
}

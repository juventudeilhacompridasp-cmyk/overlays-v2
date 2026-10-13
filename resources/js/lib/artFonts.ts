// Fontes web das artes (ADR 0009, parte 2): só o subconjunto latino (cobre o português). O carregamento é sob demanda: o CSS
// declara as fontes e `ensureFonts` pede ao navegador as que a arte usa ANTES de desenhar no canvas.
import '@fontsource/anton/latin-400.css';
import '@fontsource/archivo-black/latin-400.css';
import '@fontsource/barlow-condensed/latin-400.css';
import '@fontsource/barlow-condensed/latin-700.css';
import '@fontsource/bebas-neue/latin-400.css';
import '@fontsource/inter/latin-400.css';
import '@fontsource/inter/latin-700.css';
import '@fontsource/inter/latin-900.css';
import '@fontsource/montserrat/latin-400.css';
import '@fontsource/montserrat/latin-700.css';
import '@fontsource/montserrat/latin-900.css';
import '@fontsource/oswald/latin-400.css';
import '@fontsource/oswald/latin-700.css';
import '@fontsource/permanent-marker/latin-400.css';
import '@fontsource/playfair-display/latin-400.css';
import '@fontsource/playfair-display/latin-700.css';
import '@fontsource/playfair-display/latin-900.css';
import '@fontsource/roboto-condensed/latin-400.css';
import '@fontsource/roboto-condensed/latin-700.css';
import type { ArtStyle, FontKey } from './arts';

/** Família web de cada fonte do catálogo (as demais são do sistema) e os pesos disponíveis. */
export const WEB_FONTS: Partial<Record<FontKey, { family: string; weights: number[] }>> = {
    inter: { family: 'Inter', weights: [400, 700, 900] },
    oswald: { family: 'Oswald', weights: [400, 700] },
    bebas: { family: 'Bebas Neue', weights: [400] },
    anton: { family: 'Anton', weights: [400] },
    montserrat: { family: 'Montserrat', weights: [400, 700, 900] },
    'roboto-condensed': { family: 'Roboto Condensed', weights: [400, 700] },
    playfair: { family: 'Playfair Display', weights: [400, 700, 900] },
    marker: { family: 'Permanent Marker', weights: [400] },
    barlow: { family: 'Barlow Condensed', weights: [400, 700] },
    archivo: { family: 'Archivo Black', weights: [400] },
};

/** Garante que as fontes usadas pela arte estão carregadas (falha silenciosa: cai na fonte de reserva da pilha). */
export async function ensureFonts(style: ArtStyle): Promise<void> {
    if (typeof document === 'undefined' || !document.fonts) return;

    const used = new Set<FontKey>([style.type.heading, style.type.body]);
    const loads: Promise<unknown>[] = [];

    for (const key of used) {
        const font = WEB_FONTS[key];
        if (font) loads.push(...font.weights.map((weight) => document.fonts.load(`${weight} 32px "${font.family}"`).catch(() => [])));
    }

    await Promise.all(loads);
}

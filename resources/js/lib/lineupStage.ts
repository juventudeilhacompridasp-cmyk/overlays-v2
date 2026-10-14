/**
 * Troca de etapa da escalação por equipe (código puro, testado em Node): a etapa que sai e a que entra são animadas por
 * classes CSS (só transform/opacity/clip-path, seguro no Chromium do OBS) e a que sai fica na tela pelo tempo da animação.
 */
export type LineupTransition = 'fade' | 'slide' | 'wipe' | 'zoom' | 'flip';

export const LINEUP_TRANSITIONS: { key: LineupTransition; label: string }[] = [
    { key: 'slide', label: 'Deslize (sai para a esquerda, entra pela direita)' },
    { key: 'fade', label: 'Fusão suave' },
    { key: 'wipe', label: 'Cortina' },
    { key: 'zoom', label: 'Zoom de estúdio' },
    { key: 'flip', label: 'Virada de página' },
];

/** Duração (ms) da saída e da entrada de cada transição; sem movimento, a troca é imediata. */
export function transitionTiming(transition: LineupTransition, motion: 'dynamic' | 'subtle' | 'none'): { exit: number; enter: number } {
    if (motion === 'none') return { exit: 0, enter: 0 };

    const base = { fade: 350, slide: 450, wipe: 500, zoom: 450, flip: 550 }[transition] ?? 450;
    const factor = motion === 'subtle' ? 0.6 : 1;

    return { exit: Math.round(base * 0.8 * factor), enter: Math.round(base * factor) };
}

export const stageEnterClass = (transition: LineupTransition) => `lineup-stage-in-${transition}`;
export const stageExitClass = (transition: LineupTransition) => `lineup-stage-out-${transition}`;

/** Chave da etapa: muda quando o conteúdo muda (grade, esquema ou outro jogador) e dispara a troca animada. */
export function stageKey(content: { kind: string; index: number }, team: string): string {
    return `${team}:${content.kind}:${content.index}`;
}

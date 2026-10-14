/**
 * Animações da escalação por equipe (código puro, testado em Node): a troca entre etapas (a que sai e a que entra) e a entrada e
 * saída do PACOTE inteiro. São classes CSS (só transform/opacity/clip-path, seguro no Chromium do OBS) e a etapa que sai fica na
 * tela pelo tempo da animação.
 */
export type LineupTransition = 'fade' | 'slide' | 'rise' | 'wipe' | 'curtain' | 'split' | 'zoom' | 'flip';
export type PackageAnimation = 'none' | LineupTransition;

export const LINEUP_TRANSITIONS: { key: LineupTransition; label: string }[] = [
    { key: 'slide', label: 'Deslize (sai para a esquerda, entra pela direita)' },
    { key: 'fade', label: 'Fusão suave' },
    { key: 'rise', label: 'Subida (entra de baixo)' },
    { key: 'wipe', label: 'Cortina lateral' },
    { key: 'curtain', label: 'Cortina vertical' },
    { key: 'split', label: 'Abertura central' },
    { key: 'zoom', label: 'Zoom de estúdio' },
    { key: 'flip', label: 'Virada de página' },
];

export const PACKAGE_ANIMATIONS: { key: PackageAnimation; label: string }[] = [{ key: 'none', label: 'Sem animação (aparece e some na hora)' }, ...LINEUP_TRANSITIONS];

const BASE_MS: Record<LineupTransition, number> = { fade: 350, slide: 450, rise: 450, wipe: 500, curtain: 500, split: 520, zoom: 450, flip: 550 };

/** Duração (ms) da saída e da entrada; `speed` em % (100 = normal, 200 = o dobro da velocidade). Sem movimento, a troca é imediata. */
export function transitionTiming(transition: LineupTransition, motion: 'dynamic' | 'subtle' | 'none', speed = 100): { exit: number; enter: number } {
    if (motion === 'none') return { exit: 0, enter: 0 };

    const factor = (motion === 'subtle' ? 0.6 : 1) / (Math.min(200, Math.max(50, speed)) / 100);
    const base = BASE_MS[transition] ?? 450;

    return { exit: Math.round(base * 0.8 * factor), enter: Math.round(base * factor) };
}

export const stageEnterClass = (transition: LineupTransition) => `lineup-stage-in-${transition}`;
export const stageExitClass = (transition: LineupTransition) => `lineup-stage-out-${transition}`;

export const packageEnterClass = (animation: PackageAnimation) => (animation === 'none' ? '' : `lineup-pkg-in-${animation}`);
export const packageExitClass = (animation: PackageAnimation) => (animation === 'none' ? '' : `lineup-pkg-out-${animation}`);

/** Cortinas deslizam dentro de uma máscara de recorte (sem animar clip-path, que não roda na GPU). */
export const stageMasked = (transition: LineupTransition) => transition === 'wipe' || transition === 'curtain';

/** Saída dos cartões em cascata: o último a entrar é o primeiro a sair; as demais animações saem junto com o bloco. */
export function cardLeaveDelay(animation: string, index: number, count: number): number | null {
    return animation === 'cascade' ? Math.max(0, count - 1 - Math.min(index, 19)) * 40 : null;
}

/** Quanto tempo (ms) a saída em cascata dos cartões adiciona antes de o bloco sair. */
export function cardLeaveTotal(animation: string, count: number): number {
    return animation === 'cascade' ? Math.min(count, 20) * 40 + 240 : 0;
}

/** Chave da etapa: muda quando o conteúdo muda (grade, esquema ou outro jogador) e dispara a troca animada. */
export function stageKey(content: { kind: string; index: number }, team: string): string {
    return `${team}:${content.kind}:${content.index}`;
}

/** Entrada/saída do pacote: sem animação (ou com o movimento desligado) não há tempo de saída a esperar. */
export function packageTiming(animation: PackageAnimation, motion: 'dynamic' | 'subtle' | 'none', speed = 100): { exit: number; enter: number } {
    return animation === 'none' ? { exit: 0, enter: 0 } : transitionTiming(animation, motion, speed);
}

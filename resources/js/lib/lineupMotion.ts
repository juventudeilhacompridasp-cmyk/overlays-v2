/**
 * Pacotes de animação da escalação (código puro, testado em Node): cada um escolhe, de uma vez, a animação de entrada dos
 * cartões, a troca entre as etapas e a entrada/saída do pacote inteiro. Dá para ajustar cada item depois.
 */
import type { LineupAnimation } from './lineupSkin';
import type { LineupTransition, PackageAnimation } from './lineupStage';

export interface MotionChoice {
    animation: LineupAnimation;
    transition: LineupTransition;
    package_animation: PackageAnimation;
}

export const LINEUP_MOTION_PRESETS: { key: string; label: string; hint: string; settings: MotionChoice }[] = [
    { key: 'tv', label: 'Clássico de TV', hint: 'Deslizes laterais e subida do bloco', settings: { animation: 'slide', transition: 'slide', package_animation: 'rise' } },
    { key: 'smooth', label: 'Suave', hint: 'Fusões discretas, sem movimento brusco', settings: { animation: 'rise', transition: 'fade', package_animation: 'fade' } },
    { key: 'impact', label: 'Impacto', hint: 'Zooms de estúdio', settings: { animation: 'zoom', transition: 'zoom', package_animation: 'zoom' } },
    { key: 'studio', label: 'Estúdio', hint: 'Cortinas que abrem e fecham', settings: { animation: 'wipe', transition: 'wipe', package_animation: 'curtain' } },
    { key: 'dynamic', label: 'Dinâmico', hint: 'Cascata, viradas de página e entrada lateral', settings: { animation: 'cascade', transition: 'flip', package_animation: 'slide' } },
    { key: 'cinema', label: 'Cinema', hint: 'Aberturas centrais', settings: { animation: 'split', transition: 'split', package_animation: 'split' } },
];

/** Pacote que corresponde às configurações atuais (null = combinação personalizada). */
export function activeMotionPreset(settings: Partial<MotionChoice>): string | null {
    return LINEUP_MOTION_PRESETS.find((preset) => preset.settings.animation === settings.animation && preset.settings.transition === settings.transition && preset.settings.package_animation === settings.package_animation)?.key ?? null;
}

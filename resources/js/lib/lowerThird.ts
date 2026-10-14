/**
 * Faixas de lance (lower thirds) do OBS: estilos, animações e textos. Código puro (testado em Node): a página só desenha
 * o que sai daqui.
 */

export type LowerThirdStyle = 'arena' | 'broadcast' | 'player' | 'minimal' | 'slash' | 'flash';
export type LowerThirdAnimation = 'slide' | 'rise' | 'zoom' | 'wipe' | 'flip' | 'bounce' | 'fade';

export interface LowerThirdSettings {
    style: LowerThirdStyle;
    animation: LowerThirdAnimation;
    seconds: number;
    kinds: string[];
    score: boolean;
    position: 'left' | 'center' | 'right';
    x: number | null;
    y: number | null;
    text_scale: number;
    card_mode: 'banner' | 'fullscreen';
}

/** Cartões que podem ocupar a tela inteira. */
export const CARD_KINDS = ['yellow_card', 'red_card', 'blue_card'];

/** O cartão deste lance sai em tela cheia? */
export function isFullscreenCard(settings: Pick<LowerThirdSettings, 'card_mode'>, kind: string): boolean {
    return settings.card_mode === 'fullscreen' && CARD_KINDS.includes(kind);
}

/** Posição livre da faixa (x e y em %); só vale com os dois. */
export function lowerThirdFreePosition(settings: Pick<LowerThirdSettings, 'x' | 'y'>): { left: string; top: string } | null {
    return typeof settings.x === 'number' && typeof settings.y === 'number' ? { left: `${settings.x}%`, top: `${settings.y}%` } : null;
}

export interface LowerThirdBannerData {
    id: string;
    kind: string;
    title: string;
    subtitle: string | null;
    team: string | null;
    color: string | null;
    minute: number | null;
    team_short?: string | null;
    team_logo?: string | null;
    player?: { name: string; shirt: number | null; position: string | null } | null;
    score?: { home: number; away: number; home_short: string; away_short: string } | null;
    detail?: string | null;
}

export const LOWER_THIRD_STYLES: { key: LowerThirdStyle; label: string; hint: string }[] = [
    { key: 'arena', label: 'Arena', hint: 'Cartão de alto contraste com ícone do lance' },
    { key: 'broadcast', label: 'Barra de TV', hint: 'Barra larga com a cor da equipe e o placar' },
    { key: 'player', label: 'Cartão do jogador', hint: 'Camisa em destaque, nome e posição' },
    { key: 'minimal', label: 'Minimal', hint: 'Só texto com sublinhado de destaque' },
    { key: 'slash', label: 'Esportivo', hint: 'Blocos em diagonal com a cor da equipe' },
    { key: 'flash', label: 'Flash de gol', hint: 'Faixa de ponta a ponta para o momento especial' },
];

export const LOWER_THIRD_ANIMATIONS: { key: LowerThirdAnimation; label: string }[] = [
    { key: 'slide', label: 'Deslize' },
    { key: 'rise', label: 'Subida' },
    { key: 'zoom', label: 'Zoom' },
    { key: 'wipe', label: 'Cortina' },
    { key: 'flip', label: 'Virada 3D' },
    { key: 'bounce', label: 'Salto elástico' },
    { key: 'fade', label: 'Fade' },
];

export const LOWER_THIRD_KINDS: { key: string; label: string }[] = [
    { key: 'goal', label: 'Gol' },
    { key: 'own_goal', label: 'Gol contra' },
    { key: 'yellow_card', label: 'Cartão amarelo' },
    { key: 'red_card', label: 'Cartão vermelho' },
    { key: 'blue_card', label: 'Cartão azul' },
    { key: 'substitution', label: 'Substituição' },
];

const TONES: Record<string, { accent: string; icon: string; ink: string }> = {
    goal: { accent: '#16a34a', icon: '⚽', ink: '#ffffff' },
    own_goal: { accent: '#16a34a', icon: '⚽', ink: '#ffffff' },
    yellow_card: { accent: '#eab308', icon: '🟨', ink: '#111827' },
    red_card: { accent: '#dc2626', icon: '🟥', ink: '#ffffff' },
    blue_card: { accent: '#2563eb', icon: '🟦', ink: '#ffffff' },
    substitution: { accent: '#475569', icon: '🔁', ink: '#ffffff' },
    player: { accent: '#0ea5e9', icon: '👤', ink: '#ffffff' },
    custom: { accent: '#6366f1', icon: '🎙️', ink: '#ffffff' },
};

/** Cor, ícone e cor do texto sobre a cor do lance. */
export function lowerThirdTone(kind: string): { accent: string; icon: string; ink: string } {
    return TONES[kind] ?? TONES.goal;
}

/** Cor da equipe quando válida (#rrggbb); senão a cor do lance. */
export function teamAccent(banner: LowerThirdBannerData): string {
    return /^#[0-9a-f]{6}$/i.test(banner.color ?? '') ? (banner.color as string) : lowerThirdTone(banner.kind).accent;
}

/** Texto legível (preto ou branco) sobre uma cor de fundo. */
export function inkOn(color: string): string {
    const hex = /^#[0-9a-f]{6}$/i.test(color) ? color.slice(1) : '000000';
    const [r, g, b] = [0, 2, 4].map((offset) => parseInt(hex.slice(offset, offset + 2), 16));

    return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#111827' : '#ffffff';
}

export function scoreLine(banner: LowerThirdBannerData): string | null {
    const score = banner.score;

    return score ? `${score.home_short} ${score.home} × ${score.away} ${score.away_short}` : null;
}

export function shirtLabel(banner: LowerThirdBannerData): string | null {
    const shirt = banner.player?.shirt;

    return shirt === null || shirt === undefined ? null : String(shirt).padStart(2, '0');
}

/** Quadros (de fora para dentro) da animação de entrada; a saída toca os mesmos quadros ao contrário. */
export function lowerThirdFrames(animation: LowerThirdAnimation, centered = false): Keyframe[] {
    switch (animation) {
        case 'rise':
            return [{ opacity: 0, transform: 'translate3d(0, 3rem, 0)' }, { opacity: 1, transform: 'none' }];
        case 'zoom':
            return [{ opacity: 0, transform: 'scale(.8)' }, { opacity: 1, transform: 'none' }];
        case 'wipe':
            return [{ clipPath: 'inset(0 100% 0 0)', opacity: 1 }, { clipPath: 'inset(0 0 0 0)', opacity: 1 }];
        case 'flip':
            return [{ opacity: 0, transform: 'perspective(900px) rotateX(-80deg)', transformOrigin: 'bottom' }, { opacity: 1, transform: 'perspective(900px) rotateX(0)', transformOrigin: 'bottom' }];
        case 'bounce':
            return [{ opacity: 0, transform: 'translate3d(0, 5rem, 0) scale(.92)' }, { opacity: 1, transform: 'translate3d(0, -.8rem, 0) scale(1.02)', offset: 0.6 }, { opacity: 1, transform: 'none' }];
        case 'fade':
            return [{ opacity: 0 }, { opacity: 1 }];
        default:
            return [{ opacity: 0, transform: centered ? 'translate3d(0, 2rem, 0)' : 'translate3d(-110%, 0, 0)' }, { opacity: 1, transform: 'none' }];
    }
}

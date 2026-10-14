/** Estatísticas da partida no OBS (código puro, testado em Node): larguras das barras, prazo e listas de opções do painel. */

export type StatsView = 'comparative' | 'events' | 'player';
export type StatsStyle = 'broadcast' | 'glass' | 'minimal';
export type StatsAnimation = 'rise' | 'slide' | 'zoom' | 'wipe' | 'fade';

export interface StatsRow {
    key: string;
    label: string;
    home: number;
    away: number;
}

export interface StatsState {
    view: StatsView;
    style: StatsStyle;
    animation: StatsAnimation;
    scale: number;
    text_scale: number;
    x: number | null;
    y: number | null;
    duration: number;
    expires_ms: number | null;
    rows: StatsRow[] | null;
    events: {
        goals: { side: 'home' | 'away'; player: string | null; minute: number | null; own_goal: boolean }[];
        cards: { side: 'home' | 'away'; kind: string; player: string | null; minute: number | null }[] | null;
    } | null;
    player: {
        name: string;
        shirt: number | null;
        team: string | null;
        side: 'home' | 'away' | null;
        goals: number;
        cards: { yellow: number; red: number } | null;
        note: string;
        photo_url: string | null;
    } | null;
}

export const STATS_VIEWS: { key: StatsView; label: string; hint: string }[] = [
    { key: 'comparative', label: 'Comparativo', hint: 'Barras lado a lado com até 6 indicadores' },
    { key: 'events', label: 'Gols e cartões', hint: 'Gols (e cartões, se liberados) de cada equipe' },
    { key: 'player', label: 'Destaque do atleta', hint: 'Foto (se habilitada), camisa, gols e cartões' },
];

export const STATS_STYLES: { key: StatsStyle; label: string }[] = [
    { key: 'broadcast', label: 'Painel TV' },
    { key: 'glass', label: 'Vidro' },
    { key: 'minimal', label: 'Minimal' },
];

export const STATS_ANIMATIONS: { key: StatsAnimation; label: string }[] = [
    { key: 'rise', label: 'Subida' },
    { key: 'slide', label: 'Deslize' },
    { key: 'zoom', label: 'Zoom' },
    { key: 'wipe', label: 'Cortina' },
    { key: 'fade', label: 'Fade' },
];

export const STATS_METRICS: { key: string; label: string }[] = [
    { key: 'possession', label: 'Posse de bola' },
    { key: 'shots', label: 'Finalizações' },
    { key: 'shots_on_target', label: 'Finalizações no gol' },
    { key: 'corners', label: 'Escanteios' },
    { key: 'fouls', label: 'Faltas' },
    { key: 'offsides', label: 'Impedimentos' },
    { key: 'saves', label: 'Defesas' },
];

/** Larguras (%) das duas metades de uma barra: soma 100; sem nenhum lance fica meio a meio. */
export function barShares(home: number, away: number): { home: number; away: number } {
    const total = home + away;
    if (total <= 0) return { home: 50, away: 50 };
    const left = Math.round((home / total) * 100);

    return { home: left, away: 100 - left };
}

/** A tela já passou do prazo definido pelo servidor? (sem prazo = fica até o operador ocultar) */
export function isExpired(expiresMs: number | null, nowMs: number): boolean {
    return expiresMs !== null && nowMs >= expiresMs;
}

/** Quanto falta (ms) para sair sozinha; null se não tem prazo. */
export function remainingMs(expiresMs: number | null, nowMs: number): number | null {
    return expiresMs === null ? null : Math.max(0, expiresMs - nowMs);
}

/** Lado em destaque de um indicador (quem tem mais); empate = nenhum. Faltas e impedimentos também contam "mais" como destaque neutro. */
export function leader(row: Pick<StatsRow, 'home' | 'away'>): 'home' | 'away' | null {
    return row.home === row.away ? null : row.home > row.away ? 'home' : 'away';
}

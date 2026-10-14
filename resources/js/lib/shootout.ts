/**
 * Disputa de pênaltis no overlay (código puro, testado em Node): bolinhas de cada equipe e legendas. Só lados e resultados
 * (convertida/perdida), nunca cobradores.
 */
export interface ShootoutData {
    home: boolean[];
    away: boolean[];
    goals: { home: number; away: number };
    first: 'home' | 'away' | null;
    next: 'home' | 'away' | null;
    decided: boolean;
    winner: 'home' | 'away' | null;
    rounds: number;
    by_kick: boolean;
}

export type Dot = 'goal' | 'miss' | 'pending';

/** Cobranças de uma equipe como bolinhas: as `rounds` regulares (as que faltam ficam pendentes) e, na morte súbita, uma por rodada. */
export function shootoutDots(kicks: boolean[], rounds: number, opponentKicks = 0): Dot[] {
    const length = Math.max(rounds, kicks.length, opponentKicks);

    return Array.from({ length }, (_, index) => (index < kicks.length ? (kicks[index] ? 'goal' : 'miss') : 'pending'));
}

/** As duas fileiras de bolinhas, com o mesmo comprimento para ficarem alinhadas. */
export function shootoutRows(data: ShootoutData): { home: Dot[]; away: Dot[] } {
    return { home: shootoutDots(data.home, data.rounds, data.away.length), away: shootoutDots(data.away, data.rounds, data.home.length) };
}

/** Em morte súbita quando algum lado já passou das cobranças regulares. */
export function isSuddenDeath(data: ShootoutData): boolean {
    return Math.max(data.home.length, data.away.length) > data.rounds;
}

/** Legenda: situação da disputa em uma frase curta. */
export function shootoutCaption(data: ShootoutData, names: { home: string; away: string }): string {
    if (data.decided && data.winner) return `${names[data.winner]} vence nos pênaltis`;
    if (data.home.length + data.away.length === 0) return 'Aguardando a primeira cobrança';
    if (isSuddenDeath(data)) return 'Morte súbita';

    return data.next ? `Cobra: ${names[data.next]}` : 'Disputa de pênaltis';
}

/** Placar da disputa para o placar principal: "4 x 3". */
export function shootoutScore(data: ShootoutData): string {
    return `${data.goals.home} x ${data.goals.away}`;
}

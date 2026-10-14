/** Comemoração de gol no placar (código puro, sem React): quem marcou e em que fase da animação está. */

export type Side = 'home' | 'away';
export type Phase = 'word' | 'team';

export interface Score {
    home: number;
    away: number;
}

/** Lado que acabou de marcar; null se o placar não subiu (inclusive correção para menos) ou subiu nos dois lados de uma vez. */
export function scoredSide(prev: Score, next: Score): Side | null {
    const home = next.home - prev.home;
    const away = next.away - prev.away;

    if (home > 0 && away <= 0) return 'home';
    if (away > 0 && home <= 0) return 'away';

    return null;
}

/** Fase em `elapsedMs` desde o gol: primeiro a palavra, depois (se houver tempo) o nome da equipe; null quando acabou. */
export function celebrationPhase(elapsedMs: number, wordSeconds: number, teamSeconds: number): Phase | null {
    if (elapsedMs < 0) return null;
    if (elapsedMs < wordSeconds * 1000) return 'word';
    if (elapsedMs < (wordSeconds + teamSeconds) * 1000) return 'team';

    return null;
}

/** Duração total da comemoração, em milissegundos. */
export function celebrationDuration(wordSeconds: number, teamSeconds: number): number {
    return (wordSeconds + teamSeconds) * 1000;
}

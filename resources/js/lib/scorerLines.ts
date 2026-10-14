export interface ScorerGoal {
    side: 'home' | 'away' | null;
    player: string | null;
    minute: number | null;
    own_goal: boolean;
}

/** Autores dos gols de um lado, agrupados por jogador: "Rafa 12', 55'" (gol contra marcado). */
export function scorerLines(goals: ScorerGoal[], side: 'home' | 'away'): string[] {
    const byPlayer = new Map<string, string[]>();

    for (const goal of goals.filter((item) => item.side === side)) {
        const name = `${goal.player ?? 'Gol'}${goal.own_goal ? ' (contra)' : ''}`;
        byPlayer.set(name, [...(byPlayer.get(name) ?? []), goal.minute !== null ? `${goal.minute}'` : '']);
    }

    return [...byPlayer.entries()].map(([name, minutes]) => `${name} ${minutes.filter(Boolean).join(', ')}`.trim());
}

/** Regras puras da escala de árbitros e mesários (tela `Panel/Stages/Officials`). */

export type Selection = Record<string, string>;

/** Pessoas escolhidas em mais de uma função no mesmo jogo (a escala aceita uma função por pessoa). */
export function duplicated(selection: Selection): string[] {
    const seen = new Set<string>();
    const repeated = new Set<string>();

    Object.values(selection).forEach((id) => {
        if (!id) return;
        if (seen.has(id)) repeated.add(id);
        seen.add(id);
    });

    return [...repeated];
}

/** A escolha atual é igual à gravada? (vazio e ausente contam como o mesmo). */
export function sameSelection(a: Selection, b: Selection, roles: { value: string }[]): boolean {
    return roles.every((role) => (a[role.value] ?? '') === (b[role.value] ?? ''));
}

/** Jogos ainda sem árbitro e que ainda aceitam escala. */
export function openGames<T extends { locked: boolean; assignments: Selection }>(games: T[]): T[] {
    return games.filter((game) => !game.locked && !game.assignments.referee);
}

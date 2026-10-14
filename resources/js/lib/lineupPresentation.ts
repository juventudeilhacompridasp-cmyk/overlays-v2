export type LineupPresentation = 'sequence' | 'list' | 'formation';
export type LineupPhase = 'list' | 'formation';

export interface LineupPresentationPlan {
    initialPhase: LineupPhase;
    transitionsToFormation: boolean;
    renderList: boolean;
    renderFormation: boolean;
    homeUsesFormation: boolean;
    awayUsesFormation: boolean;
}

/**
 * Decide a apresentação sem inventar posições. Uma equipe pronta pode ir ao campo tático enquanto a outra permanece
 * na lista; sem nenhuma formação válida, até o modo tático cai de volta para a lista completa.
 */
export function lineupPresentationPlan(presentation: LineupPresentation, homeReady: boolean, awayReady: boolean): LineupPresentationPlan {
    const hasFormation = homeReady || awayReady;
    const formationRequested = presentation === 'formation' && hasFormation;
    const transitionsToFormation = presentation === 'sequence' && hasFormation;

    return {
        initialPhase: formationRequested ? 'formation' : 'list',
        transitionsToFormation,
        renderList: presentation !== 'formation' || !hasFormation,
        renderFormation: presentation !== 'list' && hasFormation,
        homeUsesFormation: homeReady,
        awayUsesFormation: awayReady,
    };
}

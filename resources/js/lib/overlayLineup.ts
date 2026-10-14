import { LineupPublicSettings, LineupSide } from './overlay';

export interface Individual {
    kind: 'player' | 'coach';
    shirt: number | null;
    name: string;
    position: string | null;
    position_label: string | null;
    captain: boolean;
    photo_url: string | null;
    photo_large_url: string | null;
}

/** Quem a apresentação individual percorre: titulares, reservas (escopo `all`) e o técnico. Mesma conta de `OverlayState::individualCount`. */
export function individuals(side: LineupSide, scope: LineupPublicSettings['individual_scope']): Individual[] {
    const players: Individual[] = side.starters.map((player) => ({ kind: 'player', shirt: player.shirt, name: player.name, position: player.position, position_label: player.position_label, captain: player.captain, photo_url: player.photo_url, photo_large_url: player.photo_large_url }));

    if (scope === 'all') {
        side.bench.forEach((player) => players.push({ kind: 'player', shirt: player.shirt, name: player.name, position: null, position_label: 'Reserva', captain: player.captain, photo_url: player.photo_url, photo_large_url: player.photo_large_url }));
    }

    if (side.coach) players.push({ kind: 'coach', shirt: null, name: side.coach, position: null, position_label: 'Técnico', captain: false, photo_url: null, photo_large_url: null });

    return players;
}

export type StepKind = 'grid' | 'formation' | 'individual';

export interface PlannedStep {
    kind: StepKind;
    ms: number;
}

type PlanSettings = Pick<LineupPublicSettings, 'presentation' | 'list_seconds' | 'individual_seconds'> & Partial<Pick<LineupPublicSettings, 'formation_seconds' | 'full_individuals'>>;

/**
 * Etapas da apresentação por equipe, na ordem: `show` = grade e cada jogador; `individual` = só os jogadores; `full` = grade,
 * esquema tático (só se a equipe tem formação) e (opcional) cada jogador; as demais, uma só etapa (a grade). Mesma regra de
 * `OverlayLineupTimeline::plan`.
 */
export function lineupPlan(settings: PlanSettings, count: number, formation = false): PlannedStep[] {
    const list = settings.list_seconds * 1000;
    const each = settings.individual_seconds * 1000;
    const people = (): PlannedStep[] => Array.from({ length: count }, () => ({ kind: 'individual' as const, ms: each }));

    switch (settings.presentation) {
        case 'show': return [{ kind: 'grid', ms: list }, ...people()];
        case 'individual': return count > 0 ? people() : [{ kind: 'individual', ms: each }];
        case 'full': return [{ kind: 'grid', ms: list }, ...(formation ? [{ kind: 'formation' as const, ms: (settings.formation_seconds ?? 8) * 1000 }] : []), ...(settings.full_individuals === false ? [] : people())];
        default: return [{ kind: 'grid', ms: list }];
    }
}

/** Depois da última etapa `show` e `full` voltam à grade; `individual` fica no último jogador. */
const wraps = (presentation: LineupPublicSettings['presentation']) => presentation === 'show' || presentation === 'full';

/** Número de etapas. Para `full` informe também as opções (esquema pronto e jogador a jogador). */
export function stepCount(presentation: LineupPublicSettings['presentation'], count: number, options: { formation?: boolean; individuals?: boolean } = {}): number {
    return lineupPlan({ presentation, list_seconds: 1, individual_seconds: 1, full_individuals: options.individuals ?? true }, count, options.formation ?? false).length;
}

export function stepStarts(settings: PlanSettings, count: number, formation = false): number[] {
    let at = 0;

    return lineupPlan(settings, count, formation).map((step) => {
        const start = at;
        at += step.ms;

        return start;
    });
}

/** O que a etapa mostra: grade, esquema tático ou o jogador de `index` (posição na lista de `individuals`). */
export function stepContent(settings: PlanSettings, count: number, step: number, formation = false): { kind: StepKind; index: number } {
    const plan = lineupPlan(settings, count, formation);
    const kind = plan[Math.min(Math.max(0, step), plan.length - 1)]?.kind ?? 'grid';
    const index = kind === 'individual' ? plan.slice(0, Math.min(Math.max(0, step), plan.length - 1)).filter((item) => item.kind === 'individual').length : 0;

    return { kind, index };
}

/**
 * Etapa mostrada agora. Automático: sai do relógio do servidor (`anchor_ms`), então todas as telas abertas concordam; ao
 * terminar, `show` e `full` voltam à grade e `individual` fica no último jogador. Manual: vale `step`, sempre dentro do limite.
 */
export function currentStep(settings: LineupPublicSettings, count: number, serverNowMs: number, formation = false): number {
    const plan = lineupPlan(settings, count, formation);
    const total = plan.length;

    if (!['show', 'individual', 'full'].includes(settings.presentation)) return 0;
    if (settings.individual_mode === 'manual') return Math.min(Math.max(0, settings.step), total - 1);

    const starts = stepStarts(settings, count, formation);
    const elapsed = Math.max(0, serverNowMs - settings.anchor_ms);
    const last = starts[total - 1] + plan[total - 1].ms;

    if (elapsed >= last) return wraps(settings.presentation) ? 0 : total - 1;

    let step = 0;
    starts.forEach((start, index) => {
        if (elapsed >= start) step = index;
    });

    return step;
}

/** Quando a etapa atual acaba (ms de relógio do servidor), para o cliente agendar a próxima atualização; null = nada a agendar. */
export function nextChangeAt(settings: LineupPublicSettings, count: number, serverNowMs: number, formation = false): number | null {
    if (!['show', 'individual', 'full'].includes(settings.presentation) || settings.individual_mode === 'manual') return null;

    const plan = lineupPlan(settings, count, formation);
    const starts = stepStarts(settings, count, formation);
    const elapsed = Math.max(0, serverNowMs - settings.anchor_ms);
    const next = starts.find((start) => start > elapsed);
    const end = starts[plan.length - 1] + plan[plan.length - 1].ms;

    if (next !== undefined) return settings.anchor_ms + next;

    return elapsed < end ? settings.anchor_ms + end : null;
}

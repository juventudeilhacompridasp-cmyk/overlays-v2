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

/** Etapas: em `show` a 0 é a grade e 1..n os jogadores; em `individual` 0..n-1 são os jogadores; nas demais, uma só. Mesma regra de `OverlayLineupTimeline`. */
export function stepCount(presentation: LineupPublicSettings['presentation'], count: number): number {
    return presentation === 'show' ? count + 1 : presentation === 'individual' ? Math.max(1, count) : 1;
}

export function stepStarts(settings: Pick<LineupPublicSettings, 'presentation' | 'list_seconds' | 'individual_seconds'>, count: number): number[] {
    const starts: number[] = [];
    let at = 0;

    for (let step = 0; step < stepCount(settings.presentation, count); step++) {
        starts.push(at);
        at += (settings.presentation === 'show' && step === 0 ? settings.list_seconds : settings.individual_seconds) * 1000;
    }

    return starts;
}

/**
 * Etapa mostrada agora. Automático: sai do relógio do servidor (`anchor_ms`), então todas as telas abertas concordam; ao
 * terminar, `show` volta à grade e `individual` fica no último jogador. Manual: vale `step`, sempre dentro do limite.
 */
export function currentStep(settings: LineupPublicSettings, count: number, serverNowMs: number): number {
    const total = stepCount(settings.presentation, count);

    if (settings.presentation !== 'show' && settings.presentation !== 'individual') return 0;
    if (settings.individual_mode === 'manual') return Math.min(Math.max(0, settings.step), total - 1);

    const starts = stepStarts(settings, count);
    const elapsed = Math.max(0, serverNowMs - settings.anchor_ms);
    const last = starts[total - 1] + (settings.presentation === 'show' && total === 1 ? settings.list_seconds : settings.individual_seconds) * 1000;

    if (elapsed >= last) return settings.presentation === 'show' ? 0 : total - 1;

    let step = 0;
    starts.forEach((start, index) => {
        if (elapsed >= start) step = index;
    });

    return step;
}

/** Quando a etapa atual acaba (ms de relógio do servidor), para o cliente agendar a próxima atualização; null = nada a agendar. */
export function nextChangeAt(settings: LineupPublicSettings, count: number, serverNowMs: number): number | null {
    if ((settings.presentation !== 'show' && settings.presentation !== 'individual') || settings.individual_mode === 'manual') return null;

    const starts = stepStarts(settings, count);
    const elapsed = Math.max(0, serverNowMs - settings.anchor_ms);
    const next = starts.find((start) => start > elapsed);
    const total = stepCount(settings.presentation, count);
    const end = starts[total - 1] + settings.individual_seconds * 1000;

    if (next !== undefined) return settings.anchor_ms + next;

    return elapsed < end ? settings.anchor_ms + end : null;
}

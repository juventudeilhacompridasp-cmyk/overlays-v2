/**
 * Fila offline do mesário: o que a tela mostra enquanto há lançamentos guardados no aparelho e ainda não enviados.
 *
 * É só uma PROJEÇÃO para a tela (placar, quem está em campo, relógio): o servidor continua sendo a autoridade e reaplica
 * cada item com a validação do regulamento quando a conexão volta. Código puro (sem React) para poder ser testado em Node.
 */

export type QueueKind = 'event' | 'point' | 'quick_result' | 'shootout_kick' | 'shootout_undo' | 'period' | 'clock' | 'shot_clock' | 'void' | 'finish';

export interface QueueItem {
    /** Chave gerada no aparelho: o servidor a usa para não aplicar o mesmo item duas vezes. */
    key: string;
    kind: QueueKind;
    payload: Record<string, unknown>;
    /** Hora do lance, em ms, alinhada ao relógio do servidor. */
    at: number;
    /** Nasceu sem conexão (ou atrás de outros itens): se o servidor recusar, fica na fila para o mesário decidir. */
    offline: boolean;
    status: 'pending' | 'blocked';
    message?: string;
    /** Descrição curta para a lista de pendências. */
    label: string;
}

export interface ClockView {
    state: 'not_started' | 'running' | 'paused' | 'stopped';
    period: number | null;
    base_seconds: number;
    running_since_ms: number | null;
}

export interface TeamView {
    on_field: string[];
    available_bench: string[];
    expelled: string[];
    fouled_out: string[];
    player_fouls: Record<string, number>;
    yellows: Record<string, number>;
    substitutions_used: number;
    timeouts?: { used: number; limit: number } | null;
}

export interface SheetView<C extends ClockView = ClockView> {
    home_id: string | null;
    away_id: string | null;
    home_score: number;
    away_score: number;
    teams: Record<string, TeamView>;
    clock: C;
    shot_clock?: ClockView | null;
    /** Há um encerramento do jogo na fila. */
    finishing: boolean;
    /** Ids de lançamentos que têm anulação na fila. */
    voided: string[];
    /** Algum efeito dos itens da fila a projeção não sabe calcular (sets, faltas da equipe, anulações): aparece depois do envio. */
    partial: boolean;
}

export interface ProjectionRules {
    /** Substituição volante: quem sai volta a ser reserva (futsal). */
    reentry: boolean;
    maxPlayerFouls: number | null;
    /** Segundo do relógio em que cada período começa (índice = período - 1). */
    offsets: number[];
    scoreModel: string;
}

export const POINT_VALUE: Record<string, number> = { free_throw: 1, field_goal: 2, three_pointer: 3 };

const without = (list: string[], id: string): string[] => list.filter((item) => item !== id);

const include = (list: string[], id: string): string[] => (list.includes(id) ? list : [...list, id]);

function elapsedAt(clock: ClockView, at: number): number {
    if (clock.state === 'running' && clock.running_since_ms !== null) {
        return clock.base_seconds + Math.max(0, Math.floor((at - clock.running_since_ms) / 1000));
    }

    return clock.base_seconds;
}

function shotRemainingAt(clock: ClockView, at: number): number {
    if (clock.state === 'running' && clock.running_since_ms !== null) {
        return Math.max(0, clock.base_seconds - Math.floor((at - clock.running_since_ms) / 1000));
    }

    return clock.base_seconds;
}

function applyShotClockMark(clock: ClockView, mark: 'start' | 'end' | 'pause' | 'resume', period: number, at: number): ClockView {
    if (mark === 'start') return { state: 'running', period, base_seconds: 24, running_since_ms: at };
    if (mark === 'end') return { ...clock, state: 'stopped', base_seconds: shotRemainingAt(clock, at), running_since_ms: null };
    if (mark === 'pause') return clock.state === 'running' ? { ...clock, state: 'paused', base_seconds: shotRemainingAt(clock, at), running_since_ms: null } : clock;
    return clock.state === 'paused' ? { ...clock, state: 'running', running_since_ms: clock.base_seconds > 0 ? at : null } : clock;
}

/** Mesmas transições do GameClock do servidor, aplicadas ao retrato do relógio. */
export function applyClockMark<C extends ClockView>(clock: C, mark: 'start' | 'end' | 'pause' | 'resume', period: number, at: number, offsets: number[]): C {
    switch (mark) {
        case 'start':
            return { ...clock, state: 'running', period, base_seconds: offsets[period - 1] ?? 0, running_since_ms: at } as C;
        case 'end':
            return clock.period === period && (clock.state === 'running' || clock.state === 'paused')
                ? ({ ...clock, state: 'stopped', base_seconds: elapsedAt(clock, at), running_since_ms: null } as C)
                : clock;
        case 'pause':
            return clock.state === 'running' ? ({ ...clock, state: 'paused', base_seconds: elapsedAt(clock, at), running_since_ms: null } as C) : clock;
        case 'resume':
            return clock.state === 'paused' ? ({ ...clock, state: 'running', running_since_ms: at } as C) : clock;
    }
}

function expel(team: TeamView, personId: string): void {
    team.expelled = include(team.expelled, personId);
    team.on_field = without(team.on_field, personId);
}

function applyEvent(view: SheetView, item: QueueItem, rules: ProjectionRules): void {
    const payload = item.payload;
    const type = String(payload.type ?? '');
    const entryId = String(payload.entry_id ?? '');
    const personId = payload.person_id ? String(payload.person_id) : null;
    const team = view.teams[entryId];

    const score = (toEntry: string, points: number) => {
        if (toEntry === view.home_id) view.home_score += points;
        else if (toEntry === view.away_id) view.away_score += points;
    };
    const other = entryId === view.home_id ? view.away_id : view.home_id;

    switch (type) {
        case 'goal':
            score(entryId, 1);
            break;
        case 'own_goal':
            if (other) score(other, 1);
            break;
        case 'free_throw':
        case 'field_goal':
        case 'three_pointer':
            score(entryId, POINT_VALUE[type]);
            break;
        case 'yellow_card':
            if (team && personId) {
                team.yellows = { ...team.yellows, [personId]: (team.yellows[personId] ?? 0) + 1 };
                if (team.yellows[personId] >= 2) expel(team, personId);
            }
            break;
        case 'red_card':
            if (team && personId) expel(team, personId);
            break;
        case 'timeout':
            if (team?.timeouts) team.timeouts = { ...team.timeouts, used: team.timeouts.used + 1 };
            break;
        case 'foul':
        case 'technical_foul':
        case 'unsportsmanlike_foul': {
            // Faltas acumuladas da equipe (contador por tempo) só o servidor calcula.
            // Desqualificação (2ª técnica/antidesportiva) também só vale depois do envio.
            view.partial = true;
            if (team && personId) {
                const total = (team.player_fouls[personId] ?? 0) + 1;
                team.player_fouls = { ...team.player_fouls, [personId]: total };
                if (rules.maxPlayerFouls !== null && total >= rules.maxPlayerFouls) team.fouled_out = include(team.fouled_out, personId);
            }
            break;
        }
        case 'substitution': {
            const incoming = payload.secondary_person_id ? String(payload.secondary_person_id) : null;
            if (team && personId && incoming) {
                team.on_field = include(without(team.on_field, personId), incoming);
                team.available_bench = without(team.available_bench, incoming);
                if (rules.reentry) team.available_bench = include(team.available_bench, personId);
                team.substitutions_used += 1;
            }
            break;
        }
        default:
            break;
    }
}

/** Aplica os itens da fila, em ordem, sobre o retrato que veio do servidor. Não altera o original. */
export function project<C extends ClockView>(base: SheetView<C>, items: QueueItem[], rules: ProjectionRules): SheetView<C> {
    const view: SheetView<C> = structuredClone(base);

    for (const item of items) {
        switch (item.kind) {
            case 'event':
                applyEvent(view, item, rules);
                break;
            case 'period':
                view.clock = applyClockMark(view.clock, item.payload.start ? 'start' : 'end', Number(item.payload.period ?? 0), item.at, rules.offsets);
                if (view.shot_clock) view.shot_clock = applyShotClockMark(view.shot_clock, item.payload.start ? 'start' : 'end', Number(item.payload.period ?? 0), item.at);
                break;
            case 'clock':
                view.clock = applyClockMark(view.clock, item.payload.pause ? 'pause' : 'resume', view.clock.period ?? 0, item.at, rules.offsets);
                if (view.shot_clock) view.shot_clock = applyShotClockMark(view.shot_clock, item.payload.pause ? 'pause' : 'resume', view.shot_clock.period ?? 0, item.at);
                break;
            case 'shot_clock':
                if (view.shot_clock) {
                    view.shot_clock = { ...view.shot_clock, base_seconds: Number(item.payload.seconds ?? 24), running_since_ms: view.shot_clock.state === 'running' ? item.at : null };
                }
                break;
            case 'finish':
                view.finishing = true;
                break;
            case 'void':
                view.voided = [...view.voided, String(item.payload.event_id ?? '')];
                view.partial = true;
                break;
            case 'point':
                // Pontos de rally viram sets: o fim de cada set só o servidor sabe calcular.
                view.partial = true;
                break;
            case 'quick_result':
                view.home_score = Number(item.payload.home_score ?? view.home_score);
                view.away_score = Number(item.payload.away_score ?? view.away_score);
                view.finishing = true;
                break;
        }
    }

    return view;
}

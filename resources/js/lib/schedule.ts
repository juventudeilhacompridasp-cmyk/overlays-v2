/**
 * Agenda da fase (código puro, testado em Node): agrupar os jogos por dia e mover um jogo para outro dia mantendo o horário.
 * Datas são "hora marcada no local" (`YYYY-MM-DDTHH:mm`, sem fuso), como no resto da plataforma.
 */

export const NO_DAY = 'none';
export const DEFAULT_TIME = '16:00';

export type ScheduledGame = { id: string; scheduled_at: string | null };

export type ScheduleFilter = { query: string; status: string; from: string; to: string };

export const dayOf = (scheduledAt: string | null): string => (scheduledAt ? scheduledAt.slice(0, 10) : NO_DAY);

export const timeOf = (scheduledAt: string | null): string => (scheduledAt && scheduledAt.length >= 16 ? scheduledAt.slice(11, 16) : '');

/** Filtra a agenda sem tocar nos dados originais; `searchText` deve conter nomes, grupo, rodada e estádio. */
export function filterSchedule<T extends ScheduledGame & { status: string }>(games: readonly T[], filter: ScheduleFilter, searchText: (game: T) => string): T[] {
    const query = filter.query.trim().toLocaleLowerCase();

    return games.filter((game) => {
        if (filter.status !== '' && game.status !== filter.status) return false;
        const day = dayOf(game.scheduled_at);
        if (filter.from !== '' && (day === NO_DAY || day < filter.from)) return false;
        if (filter.to !== '' && (day === NO_DAY || day > filter.to)) return false;

        return query === '' || searchText(game).toLocaleLowerCase().includes(query);
    });
}

export const isDay = (value: string): boolean => /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(`${value}T00:00:00Z`));

/** Novo `scheduled_at` ao soltar o jogo no dia indicado: mantém o horário; sem horário usa o padrão; "sem data" limpa. */
export function moveToDay(scheduledAt: string | null, day: string): string | null {
    if (day === NO_DAY || !isDay(day)) return null;

    return `${day}T${timeOf(scheduledAt) || DEFAULT_TIME}`;
}

/** Troca só o horário (mantém o dia; sem dia, não marca nada). */
export function withTime(scheduledAt: string | null, time: string): string | null {
    if (!scheduledAt || !/^\d{2}:\d{2}$/.test(time)) return scheduledAt;

    return `${dayOf(scheduledAt)}T${time}`;
}

export type DayGroup<T> = { day: string; games: T[] };

/** Dias em ordem, cada um com os jogos por horário; "sem data" por último e sempre presente. `extraDays` cria dias vazios. */
export function groupByDay<T extends ScheduledGame>(games: readonly T[], extraDays: readonly string[] = [], direction: 'asc' | 'desc' = 'asc'): DayGroup<T>[] {
    const map = new Map<string, T[]>();

    for (const day of extraDays) if (isDay(day)) map.set(day, []);
    for (const game of games) map.set(dayOf(game.scheduled_at), [...(map.get(dayOf(game.scheduled_at)) ?? []), game]);

    const days = [...map.keys()].filter((day) => day !== NO_DAY).sort((a, b) => direction === 'asc' ? a.localeCompare(b) : b.localeCompare(a));
    const sortGames = (list: T[]) => [...list].sort((a, b) => (direction === 'asc' ? 1 : -1) * ((a.scheduled_at ?? '').localeCompare(b.scheduled_at ?? '') || a.id.localeCompare(b.id)));

    return [...days.map((day) => ({ day, games: sortGames(map.get(day) ?? []) })), { day: NO_DAY, games: map.get(NO_DAY) ?? [] }];
}

const WEEKDAYS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

/** "2026-05-10" → "dom, 10/05/2026". */
export function formatDay(day: string): string {
    if (day === NO_DAY) return 'Sem data';
    if (!isDay(day)) return day;

    const [year, month, date] = day.split('-');

    return `${WEEKDAYS[new Date(`${day}T00:00:00Z`).getUTCDay()]}, ${date}/${month}/${year}`;
}

/** Texto curto de cada conflito para o aviso no cartão do jogo. */
export function conflictText(conflict: { kind: 'venue' | 'team'; label: string }): string {
    return conflict.kind === 'venue' ? `Mesmo estádio em horário próximo: ${conflict.label}` : `Equipe com outro jogo em horário próximo: ${conflict.label}`;
}

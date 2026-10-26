/**
 * Sorteio e chaves (código puro, testado em Node): o estado das telas de montar grupos e confrontos arrastando equipes.
 * Tudo imutável: cada função devolve o quadro novo. `rng` (0 <= n < 1) é injetável para os testes.
 */

export type Rng = () => number;

export const POOL = 'pool';

export const groupLabel = (index: number): string => `Grupo ${index < 26 ? String.fromCharCode(65 + index) : index + 1}`;

export function shuffle<T>(items: readonly T[], rng: Rng = Math.random): T[] {
    const list = [...items];

    for (let i = list.length - 1; i > 0; i--) {
        const j = Math.floor(rng() * (i + 1));
        [list[i], list[j]] = [list[j], list[i]];
    }

    return list;
}

/* ---------------------------------- Grupos ---------------------------------- */

export type DrawGroup = { id: string; name: string; teams: string[] };
export type GroupsBoard = { pool: string[]; groups: DrawGroup[] };

const groupId = (index: number) => `g${index}`;

export function emptyGroups(teamIds: readonly string[], count: number): GroupsBoard {
    return { pool: [...teamIds], groups: Array.from({ length: count }, (_, index) => ({ id: groupId(index), name: groupLabel(index), teams: [] })) };
}

/** Muda o número de grupos; as equipes dos grupos removidos voltam para "sem grupo". */
export function resizeGroups(board: GroupsBoard, count: number): GroupsBoard {
    const target = Math.max(1, Math.min(16, Math.floor(count)));
    const kept = board.groups.slice(0, target);
    const dropped = board.groups.slice(target).flatMap((group) => group.teams);
    const added = Array.from({ length: Math.max(0, target - kept.length) }, (_, offset) => ({ id: groupId(kept.length + offset), name: groupLabel(kept.length + offset), teams: [] as string[] }));

    return { pool: [...board.pool, ...dropped], groups: [...kept, ...added] };
}

export function renameGroup(board: GroupsBoard, id: string, name: string): GroupsBoard {
    return { ...board, groups: board.groups.map((group) => (group.id === id ? { ...group, name } : group)) };
}

/** Tira a equipe de onde estiver e coloca no destino (`pool` ou o id de um grupo), na posição `index` (padrão: no fim). */
export function moveToGroup(board: GroupsBoard, teamId: string, target: string, index?: number): GroupsBoard {
    if (target !== POOL && !board.groups.some((group) => group.id === target)) return board;

    const without = (list: string[]) => list.filter((id) => id !== teamId);
    const insert = (list: string[]) => {
        const next = without(list);
        next.splice(index === undefined ? next.length : Math.max(0, Math.min(index, next.length)), 0, teamId);
        return next;
    };

    return {
        pool: target === POOL ? insert(board.pool) : without(board.pool),
        groups: board.groups.map((group) => ({ ...group, teams: group.id === target ? insert(group.teams) : without(group.teams) })),
    };
}

/** Onde a equipe está: `pool` ou o id do grupo. */
export function locationOf(board: GroupsBoard, teamId: string): string {
    return board.groups.find((group) => group.teams.includes(teamId))?.id ?? POOL;
}

export function clearGroups(board: GroupsBoard): GroupsBoard {
    const all = [...board.pool, ...board.groups.flatMap((group) => group.teams)];

    return { pool: all, groups: board.groups.map((group) => ({ ...group, teams: [] })) };
}

/** Serpentina pela ordem de cabeça de chave: 1→A, 2→B, 3→C, 4→C, 5→B, 6→A... */
export function snakeGroups(board: GroupsBoard, orderedIds: readonly string[]): GroupsBoard {
    const cleared = clearGroups(board);
    const count = cleared.groups.length;
    const groups = cleared.groups.map((group) => ({ ...group, teams: [] as string[] }));

    orderedIds.forEach((id, index) => {
        const lap = Math.floor(index / count);
        const position = index % count;
        groups[lap % 2 === 0 ? position : count - 1 - position].teams.push(id);
    });

    return { pool: [], groups };
}

/** Sorteio simples: embaralha tudo e distribui uma a uma, de modo que os grupos fiquem equilibrados em tamanho. */
export function randomGroups(board: GroupsBoard, orderedIds: readonly string[], rng: Rng = Math.random): GroupsBoard {
    const count = board.groups.length;
    const groups = clearGroups(board).groups.map((group) => ({ ...group, teams: [] as string[] }));

    shuffle(orderedIds, rng).forEach((id, index) => groups[index % count].teams.push(id));

    return { pool: [], groups };
}

/**
 * Sorteio por potes: a lista (na ordem de cabeça de chave) é cortada em potes do tamanho do número de grupos e cada pote é
 * sorteado entre os grupos, um por grupo. Os fortes não caem juntos.
 */
export function potGroups(board: GroupsBoard, orderedIds: readonly string[], rng: Rng = Math.random): GroupsBoard {
    const count = board.groups.length;
    const groups = clearGroups(board).groups.map((group) => ({ ...group, teams: [] as string[] }));

    for (let start = 0; start < orderedIds.length; start += count) {
        const pot = shuffle(orderedIds.slice(start, start + count), rng);
        // O pote incompleto vai para grupos sorteados diferentes entre si.
        const slots = shuffle(groups.map((_, index) => index), rng);
        pot.forEach((id, index) => groups[slots[index]].teams.push(id));
    }

    return { pool: [], groups };
}

export function groupsProblems(board: GroupsBoard): string[] {
    const problems: string[] = [];
    const names = new Set<string>();

    if (board.pool.length > 0) problems.push(`${board.pool.length} equipe(s) ainda sem grupo.`);

    for (const group of board.groups) {
        const key = group.name.trim().toLowerCase();

        if (key === '') problems.push('Dê um nome a todos os grupos.');
        else if (names.has(key)) problems.push(`O nome “${group.name}” está repetido.`);
        names.add(key);

        if (group.teams.length < 2) problems.push(`${group.name || 'Grupo'} precisa de pelo menos duas equipes.`);
    }

    return [...new Set(problems)];
}

export function groupsPayload(board: GroupsBoard): { name: string; entries: string[] }[] {
    return board.groups.map((group) => ({ name: group.name.trim(), entries: group.teams }));
}

/* ------------------------------- Mata-mata ---------------------------------- */

export type KnockoutBoard = { pool: string[]; slots: (string | null)[] };

/** Quantos confrontos tem a 1ª rodada: a menor potência de 2 que comporta as equipes, dividida por 2. */
export function firstRoundPairs(teamCount: number): number {
    let size = 2;
    while (size < teamCount) size *= 2;

    return size / 2;
}

export function emptyBracket(teamIds: readonly string[]): KnockoutBoard {
    return { pool: [...teamIds], slots: Array.from({ length: firstRoundPairs(teamIds.length) * 2 }, () => null) };
}

export function slotIndexOf(board: KnockoutBoard, teamId: string): number {
    return board.slots.indexOf(teamId);
}

/** Coloca a equipe no lugar indicado; quem estava lá troca de lugar com ela (vai para onde ela estava). */
export function moveToSlot(board: KnockoutBoard, teamId: string, slot: number): KnockoutBoard {
    if (slot < 0 || slot >= board.slots.length) return board;

    const slots = [...board.slots];
    const source = slots.indexOf(teamId);
    const occupant = slots[slot];
    let pool = board.pool.filter((id) => id !== teamId);

    if (occupant === teamId) return board;

    if (source >= 0) slots[source] = occupant;
    else if (occupant !== null) pool = [...pool, occupant];

    slots[slot] = teamId;

    return { pool, slots };
}

export function moveToBracketPool(board: KnockoutBoard, teamId: string): KnockoutBoard {
    if (!board.slots.includes(teamId)) return board;

    return { pool: [...board.pool, teamId], slots: board.slots.map((id) => (id === teamId ? null : id)) };
}

export function clearBracket(board: KnockoutBoard): KnockoutBoard {
    return { pool: [...board.pool, ...board.slots.filter((id): id is string => id !== null)], slots: board.slots.map(() => null) };
}

function seedOrder(size: number): number[] {
    let order = [1];

    while (order.length < size) {
        const total = order.length * 2;
        order = order.flatMap((seed) => [seed, total + 1 - seed]);
    }

    return order;
}

/** Chaveamento clássico (1º x último, 2º x penúltimo...), igual ao do servidor: as melhores cabeças de chave folgam. */
export function seededBracket(orderedIds: readonly string[]): KnockoutBoard {
    const board = emptyBracket(orderedIds);
    const order = seedOrder(board.slots.length);

    return { pool: [], slots: order.map((seed) => orderedIds[seed - 1] ?? null) };
}

/** Sorteio puro: embaralha e distribui; as folgas (vagas vazias) ficam em confrontos diferentes, nunca dois vazios juntos. */
export function randomBracket(teamIds: readonly string[], rng: Rng = Math.random): KnockoutBoard {
    const board = emptyBracket(teamIds);
    const pairs = board.slots.length / 2;
    const slots: (string | null)[] = board.slots.map(() => null);
    const teams = shuffle(teamIds, rng);

    // 1ª passada: uma equipe em cada confronto, em ordem aleatória; 2ª: o resto nas vagas livres (b) dos confrontos.
    const firsts = shuffle(Array.from({ length: pairs }, (_, index) => index), rng);
    teams.slice(0, pairs).forEach((id, index) => (slots[firsts[index] * 2] = id));
    const seconds = shuffle(firsts, rng);
    teams.slice(pairs).forEach((id, index) => (slots[seconds[index] * 2 + 1] = id));

    return { pool: [], slots };
}

export function bracketProblems(board: KnockoutBoard): string[] {
    const problems: string[] = [];

    if (board.pool.length > 0) problems.push(`${board.pool.length} equipe(s) ainda fora da chave.`);

    for (let pair = 0; pair < board.slots.length / 2; pair++) {
        if (board.slots[pair * 2] === null && board.slots[pair * 2 + 1] === null) {
            problems.push(`O confronto ${pair + 1} está sem equipes.`);
        }
    }

    return problems;
}

export function pairsPayload(board: KnockoutBoard): [string | null, string | null][] {
    const pairs: [string | null, string | null][] = [];

    for (let pair = 0; pair < board.slots.length / 2; pair++) pairs.push([board.slots[pair * 2], board.slots[pair * 2 + 1]]);

    return pairs;
}

/** Nome das rodadas da chave (Oitavas, Quartas, Semifinal, Final) a partir do número de confrontos da 1ª rodada. */
export function roundNames(pairs: number): string[] {
    const rounds = Math.round(Math.log2(pairs * 2));

    return Array.from({ length: rounds }, (_, index) => {
        const left = rounds - 1 - index;

        return left === 0 ? 'Final' : left === 1 ? 'Semifinal' : left === 2 ? 'Quartas de final' : left === 3 ? 'Oitavas de final' : `${2 ** left} avos de final`;
    });
}

/* ------------------------- Restrição: mesma cidade --------------------------- */

/** Cidade de cada equipe, já normalizada (minúsculas, sem espaços nas pontas); vazia = sem restrição. */
export type CityOf = (teamId: string) => string;

export const normalizeCity = (city: string | null | undefined): string => (city ?? '').trim().toLowerCase();

export type Clash = { where: string; city: string; teams: string[] };

/** Equipes da mesma cidade no mesmo grupo. */
export function groupClashes(board: GroupsBoard, cityOf: CityOf): Clash[] {
    const clashes: Clash[] = [];

    for (const group of board.groups) {
        const byCity = new Map<string, string[]>();

        for (const id of group.teams) {
            const city = cityOf(id);
            if (city !== '') byCity.set(city, [...(byCity.get(city) ?? []), id]);
        }

        for (const [city, teams] of byCity) if (teams.length > 1) clashes.push({ where: group.id, city, teams });
    }

    return clashes;
}

/** Equipes da mesma cidade no mesmo confronto da 1ª rodada. */
export function bracketClashes(board: KnockoutBoard, cityOf: CityOf): Clash[] {
    const clashes: Clash[] = [];

    for (let pair = 0; pair < board.slots.length / 2; pair++) {
        const a = board.slots[pair * 2];
        const b = board.slots[pair * 2 + 1];
        if (a && b && cityOf(a) !== '' && cityOf(a) === cityOf(b)) clashes.push({ where: String(pair), city: cityOf(a), teams: [a, b] });
    }

    return clashes;
}

/** Tenta o sorteio várias vezes e fica com o que tem menos conflitos (para ao achar um sem nenhum). */
export function bestOf<T>(attempts: number, make: () => T, conflicts: (candidate: T) => number): T {
    let best = make();
    let bestScore = conflicts(best);

    for (let i = 1; i < attempts && bestScore > 0; i++) {
        const candidate = make();
        const score = conflicts(candidate);

        if (score < bestScore) {
            best = candidate;
            bestScore = score;
        }
    }

    return best;
}

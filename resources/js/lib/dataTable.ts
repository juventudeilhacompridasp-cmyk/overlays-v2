/**
 * Regras PURAS das tabelas dinâmicas (ordenar, buscar, filtrar, paginar e montar a consulta do servidor).
 * Sem React, sem DOM: testadas em Node (`tests/js/dataTable.test.mjs`). O componente `Components/DataTable.tsx` só desenha.
 */

export type SortDir = 'asc' | 'desc';
export type SortState = { key: string; dir: SortDir } | null;
export type Cell = string | number | boolean | null | undefined;

/** Minúsculas e sem acento: "Fábio" casa com "fabio". */
export function normalize(value: Cell): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();
}

/** Ordenação natural pt-BR ("2" antes de "10", acento e caixa ignorados). Criado uma vez: `Intl.Collator` é caro. */
const collator = new Intl.Collator('pt-BR', { numeric: true, sensitivity: 'base' });

/** Vazios sempre por último (nos dois sentidos); números por valor; o resto por texto natural. */
export function compareValues(a: Cell, b: Cell): number {
    const emptyA = a === null || a === undefined || a === '';
    const emptyB = b === null || b === undefined || b === '';

    if (emptyA || emptyB) {
        return emptyA === emptyB ? 0 : emptyA ? 1 : -1;
    }

    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    if (typeof a === 'boolean' && typeof b === 'boolean') {
        return Number(a) - Number(b);
    }

    return collator.compare(String(a), String(b));
}

/** Ordena sem mexer na lista original; estável (empate mantém a ordem de origem). Vazios ficam por último também em `desc`. */
export function sortRows<T>(rows: readonly T[], value: (row: T) => Cell, dir: SortDir): T[] {
    const keyed = rows.map((row, index) => ({ row, index, key: value(row) }));

    keyed.sort((x, y) => {
        const emptyX = x.key === null || x.key === undefined || x.key === '';
        const emptyY = y.key === null || y.key === undefined || y.key === '';

        if (emptyX || emptyY) {
            return emptyX === emptyY ? x.index - y.index : emptyX ? 1 : -1;
        }

        const result = compareValues(x.key, y.key);

        return (dir === 'asc' ? result : -result) || x.index - y.index;
    });

    return keyed.map((item) => item.row);
}

/** Clique no cabeçalho: ordem crescente → decrescente → volta à ordem original. */
export function nextSort(current: SortState, key: string): SortState {
    if (current === null || current.key !== key) {
        return { key, dir: 'asc' };
    }

    return current.dir === 'asc' ? { key, dir: 'desc' } : null;
}

/** Todas as palavras da busca precisam aparecer (em qualquer ordem) no texto da linha. */
export function matchesQuery(haystack: string, query: string): boolean {
    const tokens = normalize(query).split(/\s+/).filter(Boolean);

    return tokens.every((token) => haystack.includes(token));
}

export type FilterValues = Record<string, string>;

/** Filtros por coluna: valor vazio = sem filtro; senão a célula precisa ser IGUAL ao escolhido. */
export function filterRows<T>(
    rows: readonly T[],
    options: { query: string; searchText: (row: T) => string; filters: FilterValues; filterValue: (row: T, key: string) => Cell },
): T[] {
    const active = Object.entries(options.filters).filter(([, value]) => value !== '');
    const query = normalize(options.query);

    if (active.length === 0 && query === '') {
        return rows.slice();
    }

    return rows.filter((row) => {
        for (const [key, wanted] of active) {
            if (String(options.filterValue(row, key) ?? '') !== wanted) {
                return false;
            }
        }

        return query === '' || matchesQuery(normalize(options.searchText(row)), query);
    });
}

/** Opções de um filtro de seleção a partir dos próprios dados, com contagem, ordenadas. */
export function distinctOptions<T>(rows: readonly T[], value: (row: T) => Cell): { value: string; label: string; count: number }[] {
    const counts = new Map<string, number>();

    for (const row of rows) {
        const cell = value(row);

        if (cell === null || cell === undefined || cell === '') {
            continue;
        }

        counts.set(String(cell), (counts.get(String(cell)) ?? 0) + 1);
    }

    return [...counts.entries()].sort((a, b) => collator.compare(a[0], b[0])).map(([key, count]) => ({ value: key, label: key, count }));
}

export type PageInfo = { page: number; pages: number; from: number; to: number; total: number; size: number };

/** Página segura (nunca fora do intervalo, mesmo se o filtro encolheu a lista) e o intervalo "mostrando X–Y de Z". */
export function paginate(total: number, page: number, size: number): PageInfo {
    const safeSize = Math.max(1, Math.floor(size) || 1);
    const pages = Math.max(1, Math.ceil(total / safeSize));
    const current = Math.min(Math.max(1, Math.floor(page) || 1), pages);
    const from = total === 0 ? 0 : (current - 1) * safeSize + 1;

    return { page: current, pages, from, to: Math.min(total, current * safeSize), total, size: safeSize };
}

/** Janela enxuta de botões de página: 1 … 4 5 [6] 7 8 … 20 (cabe no celular). */
export function pageWindow(page: number, pages: number, around = 1): (number | '…')[] {
    const wanted = new Set<number>([1, pages, page]);

    for (let offset = 1; offset <= around; offset++) {
        wanted.add(page - offset);
        wanted.add(page + offset);
    }

    const numbers = [...wanted].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b);
    const result: (number | '…')[] = [];

    numbers.forEach((n, index) => {
        if (index > 0 && n - numbers[index - 1] > 1) {
            result.push('…');
        }

        result.push(n);
    });

    return result;
}

export const PAGE_SIZES = [10, 25, 50, 100] as const;

export type ServerState = { q: string; sort: string | null; dir: SortDir; page: number; per_page: number; filters: FilterValues };

/** Parâmetros da URL para o modo servidor: só o que foge do padrão (URL curta e compartilhável). */
export function serverParams(state: ServerState, defaults: { per_page: number; sort?: string | null; dir?: SortDir } = { per_page: 15 }): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    if (state.q.trim() !== '') {
        params.q = state.q.trim();
    }

    if (state.sort && (state.sort !== (defaults.sort ?? null) || state.dir !== (defaults.dir ?? 'asc'))) {
        params.sort = state.sort;
        params.dir = state.dir;
    }

    if (state.per_page !== defaults.per_page) {
        params.per_page = state.per_page;
    }

    if (state.page > 1) {
        params.page = state.page;
    }

    for (const [key, value] of Object.entries(state.filters)) {
        if (value !== '') {
            params[`filter[${key}]`] = value;
        }
    }

    return params;
}

/** Texto curto de resultado para leitores de tela e para o rodapé. */
export function summaryText(info: PageInfo, filtered: boolean): string {
    if (info.total === 0) {
        return filtered ? 'Nenhum resultado para os filtros.' : 'Nenhum registro.';
    }

    return `${info.from}–${info.to} de ${info.total}${filtered ? ' (filtrado)' : ''}`;
}

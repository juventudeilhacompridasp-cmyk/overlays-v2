import {
    Cell,
    FilterValues,
    PAGE_SIZES,
    ServerState,
    SortDir,
    SortState,
    distinctOptions,
    filterRows,
    nextSort,
    normalize,
    pageWindow,
    paginate,
    serverParams,
    sortRows,
    summaryText,
} from '@/lib/dataTable';
import { Paginated, TableFilters } from '@/types/panel';
import { router } from '@inertiajs/react';
import { ReactNode, useCallback, useDeferredValue, useEffect, useId, useMemo, useRef, useState } from 'react';

/**
 * Tabela dinâmica: busca, filtros por coluna, ordenação e paginação SEM recarregar a página.
 *
 * - Modo cliente (padrão): tudo na memória do navegador, instantâneo. Para listas que já vêm inteiras do servidor.
 * - Modo servidor (`server`): cada mudança vira uma visita parcial do Inertia (XHR, sem recarregar), com busca atrasada e
 *   a URL atualizada (compartilhável). Para listas grandes e paginadas no banco.
 * - Responsiva: a MESMA tabela vira cartões abaixo de `md` (um só HTML, serve ao SSR do portal), com ordenação e filtros
 *   em controles próprios para o dedo (alvos de 44 px). Colunas secundárias somem entre `md` e `lg` (`hideBelow`).
 *
 * As regras (ordenar, buscar, paginar) ficam em `lib/dataTable.ts` (puro e testado). Defina `columns` fora do componente
 * ou deixe-as simples: o índice de busca é refeito só quando `rows` muda.
 */

export interface Column<T> {
    key: string;
    header: string;
    /** O que se vê na célula. */
    cell: (row: T) => ReactNode;
    /** Valor simples para ordenar, filtrar e buscar. Sem ele a coluna não ordena nem filtra. */
    value?: (row: T) => Cell;
    sortable?: boolean;
    align?: 'left' | 'center' | 'right';
    /** Filtro de seleção: `true` monta as opções a partir dos dados; ou informe as opções (obrigatório no modo servidor). */
    filter?: boolean | { value: string; label: string }[];
    /** Entra no texto da busca (padrão: sim, se houver `value`). */
    searchable?: boolean;
    /** Esconde a coluna na tabela entre `md` e o breakpoint (ela continua no cartão do celular). */
    hideBelow?: 'lg' | 'xl';
    /** Título do cartão no celular (sem rótulo, em destaque). */
    primary?: boolean;
    /** Rótulo no cartão; `false` = sem rótulo (ex.: ações). Padrão: o cabeçalho. */
    mobileLabel?: string | false;
    /** Nome do parâmetro de ordenação no servidor (padrão: `key`). */
    sortKey?: string;
    /** No layout `table` (rolagem horizontal), mantém a coluna fixa à esquerda ao rolar (ex.: nome da equipe). */
    sticky?: boolean;
    className?: string;
    nowrap?: boolean;
}

export interface ServerConfig {
    /** Endereço da lista (ex.: `route('panel.teams.index')`). */
    url: string;
    paginator: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    state: { q: string; sort: string | null; dir: SortDir; per_page: number; filters: FilterValues };
    defaults: { per_page: number; sort?: string | null; dir?: SortDir };
    /** Props do Inertia a recarregar (menos dados na resposta). */
    only?: string[];
}

interface Props<T> {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    /** Nome da tabela para leitores de tela (e título visível se `title`). */
    caption: string;
    title?: string;
    actions?: ReactNode;
    searchPlaceholder?: string;
    /** Padrão: mostra a busca quando há mais de 8 linhas (ou no modo servidor). */
    searchable?: boolean;
    pageSize?: number;
    paginated?: boolean;
    initialSort?: SortState;
    /** Guarda ordenação e tamanho da página no navegador (por chave). */
    persistKey?: string;
    empty?: ReactNode;
    rowClassName?: (row: T) => string;
    server?: ServerConfig;
    /**
     * `cards` (padrão): no celular cada linha vira um cartão. `table`: continua tabela em qualquer largura, com rolagem
     * horizontal (para tabelas numéricas largas, como a classificação).
     */
    layout?: 'cards' | 'table';
    /** Sem borda e fundo próprios (quando já está dentro de um cartão). */
    flush?: boolean;
    className?: string;
}

const plainAlign = { left: 'text-left', center: 'text-center', right: 'text-right' } as const;
const alignMap = { left: 'md:text-left', center: 'md:text-center', right: 'md:text-right' } as const;
const hideHeader = { lg: 'hidden md:hidden lg:table-cell', xl: 'hidden md:hidden xl:table-cell' } as const;
const hideCell = { lg: 'flex md:hidden lg:table-cell', xl: 'flex md:hidden xl:table-cell' } as const;

const control =
    'h-11 rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 md:h-9';
const button =
    'inline-flex h-11 min-w-11 items-center justify-center rounded-md bg-white px-3 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-40 md:h-9 md:min-w-9';

function readPersisted(key: string | undefined): { sort: SortState; size: number | null } {
    if (!key || typeof window === 'undefined') {
        return { sort: null, size: null };
    }

    try {
        const parsed = JSON.parse(window.localStorage.getItem(`table:${key}`) ?? 'null');

        return {
            sort: parsed?.sort && typeof parsed.sort.key === 'string' && (parsed.sort.dir === 'asc' || parsed.sort.dir === 'desc') ? parsed.sort : null,
            size: PAGE_SIZES.includes(parsed?.size) ? parsed.size : null,
        };
    } catch {
        return { sort: null, size: null };
    }
}

function writePersisted(key: string | undefined, value: { sort: SortState; size: number }) {
    if (!key || typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(`table:${key}`, JSON.stringify(value));
    } catch {
        // sem armazenamento (modo privado): a tabela funciona do mesmo jeito
    }
}

export default function DataTable<T>({
    columns,
    rows,
    rowKey,
    caption,
    title,
    actions,
    searchPlaceholder = 'Buscar na tabela',
    searchable,
    pageSize = 25,
    paginated = true,
    initialSort = null,
    persistKey,
    empty,
    rowClassName,
    server,
    layout = 'cards',
    flush = false,
    className = '',
}: Props<T>) {
    const id = useId();
    const stack = layout === 'cards';
    const columnsRef = useRef(columns);
    columnsRef.current = columns;

    const persisted = useMemo(() => readPersisted(persistKey), [persistKey]);
    const [sort, setSort] = useState<SortState>(server ? (server.state.sort ? { key: server.state.sort, dir: server.state.dir } : null) : (persisted.sort ?? initialSort));
    const [size, setSize] = useState<number>(server ? server.state.per_page : (persisted.size ?? pageSize));
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState(server ? server.state.q : '');
    const [filters, setFilters] = useState<FilterValues>(server ? server.state.filters : {});
    const [showFilters, setShowFilters] = useState(false);
    const [busy, setBusy] = useState(false);
    const deferredQuery = useDeferredValue(query);

    const filterColumns = columns.filter((column) => column.filter);
    const sortable = columns.filter((column) => column.sortable ?? (column.value !== undefined));
    const activeFilters = Object.values(filters).filter((value) => value !== '').length;
    const filtered = (server ? query.trim() !== '' : deferredQuery.trim() !== '') || activeFilters > 0;
    const showSearch = searchable ?? (server !== undefined || rows.length > 8);

    // ───────────── modo cliente ─────────────
    const searchIndex = useMemo(
        () =>
            server
                ? []
                : rows.map((row) =>
                      normalize(
                          columnsRef.current
                              .filter((column) => column.value && column.searchable !== false)
                              .map((column) => column.value!(row))
                              .join(' '),
                      ),
                  ),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [rows, server],
    );

    const optionsByColumn = useMemo(() => {
        const result: Record<string, { value: string; label: string; count?: number }[]> = {};

        for (const column of columnsRef.current) {
            if (column.filter === true && column.value && !server) {
                result[column.key] = distinctOptions(rows, column.value);
            } else if (Array.isArray(column.filter)) {
                result[column.key] = column.filter;
            }
        }

        return result;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rows, server]);

    const clientRows = useMemo(() => {
        if (server) {
            return rows;
        }

        const indexOf = new Map<T, number>(rows.map((row, position) => [row, position]));
        const valueOf = (column: string) => columnsRef.current.find((item) => item.key === column)?.value;
        const visible = filterRows(rows, {
            query: deferredQuery,
            searchText: (row) => searchIndex[indexOf.get(row) ?? 0] ?? '',
            filters,
            filterValue: (row, key) => valueOf(key)?.(row),
        });
        const sortValue = sort ? valueOf(sort.key) : undefined;

        return sort && sortValue ? sortRows(visible, sortValue, sort.dir) : visible;
    }, [rows, server, deferredQuery, filters, sort, searchIndex]);

    const info = server
        ? { page: server.paginator.current_page, pages: server.paginator.last_page, from: server.paginator.from ?? 0, to: server.paginator.to ?? 0, total: server.paginator.total, size: server.paginator.per_page }
        : paginate(clientRows.length, page, paginated ? size : Math.max(clientRows.length, 1));
    const visibleRows = server || !paginated ? clientRows : clientRows.slice(info.from - 1 < 0 ? 0 : info.from - 1, info.to);

    // ───────────── modo servidor ─────────────
    const timer = useRef<ReturnType<typeof setTimeout>>();
    const visit = useCallback(
        (next: Partial<ServerState>) => {
            if (!server) {
                return;
            }

            const state: ServerState = { q: query, sort: sort?.key ?? null, dir: sort?.dir ?? 'asc', page: 1, per_page: size, filters, ...next };

            router.get(server.url, serverParams(state, server.defaults), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: server.only,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            });
        },
        [server, query, sort, size, filters],
    );

    useEffect(() => () => clearTimeout(timer.current), []);

    const changeQuery = (value: string) => {
        setQuery(value);
        setPage(1);

        if (server) {
            clearTimeout(timer.current);
            timer.current = setTimeout(() => visit({ q: value, page: 1 }), 350);
        }
    };

    const changeSort = (next: SortState) => {
        setSort(next);
        setPage(1);

        if (server) {
            visit({ sort: next?.key ?? null, dir: next?.dir ?? 'asc', page: 1 });
        } else {
            writePersisted(persistKey, { sort: next, size });
        }
    };

    const changeFilter = (key: string, value: string) => {
        const next = { ...filters, [key]: value };
        setFilters(next);
        setPage(1);

        if (server) {
            visit({ filters: next, page: 1 });
        }
    };

    const changeSize = (value: number) => {
        setSize(value);
        setPage(1);

        if (server) {
            visit({ per_page: value, page: 1 });
        } else {
            writePersisted(persistKey, { sort, size: value });
        }
    };

    const goTo = (value: number) => {
        setPage(value);

        if (server) {
            visit({ page: value });
        }
    };

    const clearAll = () => {
        setQuery('');
        setFilters({});
        setPage(1);

        if (server) {
            clearTimeout(timer.current);
            visit({ q: '', filters: {}, page: 1 });
        }
    };

    // Servidor: mantém o que a pessoa digitou/escolheu quando a resposta chega (preserveState) e sincroniza com a URL (voltar/avançar).
    useEffect(() => {
        if (server) {
            setSort(server.state.sort ? { key: server.state.sort, dir: server.state.dir } : null);
            setFilters(server.state.filters);
            setSize(server.state.per_page);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [server?.state.sort, server?.state.dir, server?.state.per_page, JSON.stringify(server?.state.filters)]);

    const sortKeyOf = (column: Column<T>) => column.sortKey ?? column.key;
    const activeSortKey = sort?.key;
    const isSorted = (column: Column<T>) => activeSortKey === (server ? sortKeyOf(column) : column.key);
    const sortFor = (column: Column<T>): SortState => nextSort(sort, server ? sortKeyOf(column) : column.key);

    const ariaSort = (column: Column<T>): 'ascending' | 'descending' | 'none' | undefined =>
        !(column.sortable ?? column.value !== undefined) ? undefined : isSorted(column) ? (sort?.dir === 'asc' ? 'ascending' : 'descending') : 'none';

    const mobileSortValue = sort ? `${sort.key}` : '';
    const summary = summaryText(info, filtered);

    return (
        <section
            className={`${flush ? '' : 'rounded-lg bg-white shadow-sm ring-1 ring-gray-200'} ${className}`}
            aria-busy={busy}
            data-testid="data-table"
        >
            {(title || actions) && (
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 md:px-5">
                    {title && <h2 className="text-base font-semibold text-gray-900">{title}</h2>}
                    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                </header>
            )}

            {(showSearch || filterColumns.length > 0 || sortable.length > 0) && (rows.length > 0 || server !== undefined || filtered) && (
                <div className="space-y-2 px-4 py-3 md:px-5">
                    <div className="flex flex-wrap items-center gap-2">
                        {showSearch && (
                            <div className="relative min-w-0 flex-1 md:max-w-sm">
                                <label htmlFor={`${id}-q`} className="sr-only">
                                    {searchPlaceholder}
                                </label>
                                <input
                                    id={`${id}-q`}
                                    type="search"
                                    inputMode="search"
                                    enterKeyHint="search"
                                    autoComplete="off"
                                    value={query}
                                    onChange={(event) => changeQuery(event.target.value)}
                                    placeholder={searchPlaceholder}
                                    className={`${control} w-full pl-3`}
                                />
                            </div>
                        )}

                        {filterColumns.length > 0 && (
                            <button type="button" className={`${button} md:hidden`} aria-expanded={showFilters} aria-controls={`${id}-filters`} onClick={() => setShowFilters(!showFilters)}>
                                Filtros{activeFilters > 0 ? ` (${activeFilters})` : ''}
                            </button>
                        )}

                        {sortable.length > 0 && (
                            <div className="flex items-center gap-1 md:hidden">
                                <label htmlFor={`${id}-sort`} className="sr-only">
                                    Ordenar por
                                </label>
                                <select
                                    id={`${id}-sort`}
                                    value={mobileSortValue}
                                    onChange={(event) => changeSort(event.target.value === '' ? null : { key: event.target.value, dir: sort?.dir ?? 'asc' })}
                                    className={`${control} max-w-40`}
                                >
                                    <option value="">Ordem padrão</option>
                                    {sortable.map((column) => (
                                        <option key={column.key} value={server ? sortKeyOf(column) : column.key}>
                                            {column.header}
                                        </option>
                                    ))}
                                </select>
                                {sort && (
                                    <button
                                        type="button"
                                        className={button}
                                        aria-label={sort.dir === 'asc' ? 'Ordem crescente; tocar para decrescente' : 'Ordem decrescente; tocar para crescente'}
                                        onClick={() => changeSort({ key: sort.key, dir: sort.dir === 'asc' ? 'desc' : 'asc' })}
                                    >
                                        {sort.dir === 'asc' ? '↑' : '↓'}
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    {filterColumns.length > 0 && (
                        <div id={`${id}-filters`} className={`${showFilters ? 'flex' : 'hidden'} flex-wrap items-end gap-2 md:flex`}>
                            {filterColumns.map((column) => (
                                <label key={column.key} className="min-w-0 flex-1 text-xs font-medium text-gray-500 sm:flex-none">
                                    {column.header}
                                    <select
                                        value={filters[column.key] ?? ''}
                                        onChange={(event) => changeFilter(column.key, event.target.value)}
                                        className={`${control} mt-1 block w-full font-normal sm:w-auto`}
                                    >
                                        <option value="">Todos</option>
                                        {(optionsByColumn[column.key] ?? []).map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                                {'count' in option && option.count !== undefined ? ` (${option.count})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            ))}
                            {(activeFilters > 0 || query !== '') && (
                                <button type="button" className={button} onClick={clearAll}>
                                    Limpar
                                </button>
                            )}
                        </div>
                    )}
                </div>
            )}

            <div role="status" aria-live="polite" className="sr-only">
                {summary}
            </div>

            {rows.length === 0 && !filtered && !server ? (
                <div className="px-4 py-8 text-center text-sm text-gray-500">{empty ?? 'Nenhum registro.'}</div>
            ) : visibleRows.length === 0 ? (
                <div className="px-4 py-8 text-center text-sm text-gray-500">
                    <p>{filtered ? 'Nenhum resultado para o que você buscou.' : (empty ?? 'Nenhum registro.')}</p>
                    {filtered && (
                        <button type="button" className={`${button} mt-3`} onClick={clearAll}>
                            Limpar busca e filtros
                        </button>
                    )}
                </div>
            ) : (
                <div className={`${stack ? 'md:overflow-x-auto' : 'overflow-x-auto'} ${busy ? 'opacity-60 transition-opacity' : ''}`}>
                    <table role="table" className={`${stack ? 'block md:table' : 'table'} w-full text-sm`}>
                        <caption className="sr-only">{caption}</caption>
                        <thead role="rowgroup" className={`${stack ? 'hidden md:table-header-group' : 'table-header-group'} bg-gray-50 text-left text-xs uppercase text-gray-500`}>
                            <tr role="row">
                                {columns.map((column) => {
                                    const canSort = column.sortable ?? column.value !== undefined;
                                    const sorted = isSorted(column);

                                    return (
                                        <th
                                            key={column.key}
                                            role="columnheader"
                                            scope="col"
                                            aria-sort={ariaSort(column)}
                                            className={`${stack ? 'px-4' : 'px-3'} py-3 font-semibold ${column.align ? (stack ? alignMap[column.align] : plainAlign[column.align]) : ''} ${column.hideBelow ? hideHeader[column.hideBelow] : ''} ${!stack && column.sticky ? 'sticky left-0 z-[1] bg-gray-50' : ''}`}
                                        >
                                            {canSort ? (
                                                <button
                                                    type="button"
                                                    onClick={() => changeSort(sortFor(column))}
                                                    className={`inline-flex items-center gap-1 rounded uppercase hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 ${sorted ? 'text-gray-900' : ''}`}
                                                >
                                                    {column.header}
                                                    <span aria-hidden="true" className={sorted ? '' : 'opacity-30'}>
                                                        {sorted ? (sort?.dir === 'asc' ? '↑' : '↓') : '↕'}
                                                    </span>
                                                </button>
                                            ) : (
                                                column.header
                                            )}
                                        </th>
                                    );
                                })}
                            </tr>
                        </thead>
                        <tbody role="rowgroup" className={`${stack ? 'block md:table-row-group' : 'table-row-group'} divide-y divide-gray-100`}>
                            {visibleRows.map((row) => (
                                <tr key={rowKey(row)} role="row" className={`${stack ? 'block px-4 py-3 md:table-row md:p-0' : 'table-row'} ${rowClassName?.(row) ?? ''}`}>
                                    {columns.map((column) => {
                                        const label = column.mobileLabel === undefined ? column.header : column.mobileLabel;
                                        const hide = column.hideBelow ? hideCell[column.hideBelow] : 'flex md:table-cell';
                                        const cellClass = stack
                                            ? `${column.primary ? 'block py-1 text-base font-medium md:table-cell md:text-sm' : `${hide} items-start justify-between gap-3 py-1`} md:px-4 md:py-3 ${column.align ? alignMap[column.align] : ''}`
                                            : `table-cell px-3 py-2 ${column.align ? plainAlign[column.align] : ''} ${column.hideBelow ? 'hidden lg:table-cell' : ''} ${column.sticky ? 'sticky left-0 z-[1] bg-white' : ''}`;

                                        return (
                                            <td key={column.key} role="cell" className={`${cellClass} ${column.nowrap || !stack ? 'whitespace-nowrap' : ''} ${column.className ?? ''}`}>
                                                {stack && !column.primary && label !== false && label !== '' && <span className="shrink-0 text-xs text-gray-500 md:hidden">{label}</span>}
                                                <span className={stack && !column.primary ? `min-w-0 text-right ${column.mobileLabel === false ? 'ml-auto' : ''}` : ''}>{column.cell(row)}</span>
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {(visibleRows.length > 0 || info.total > 0) && (
                <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-sm text-gray-600 md:px-5">
                    <span aria-hidden="true">{summary}</span>

                    {(paginated || server) && info.pages > 0 && (
                        <div className="flex flex-wrap items-center gap-2">
                            <label className="flex items-center gap-1 text-xs text-gray-500">
                                <span className="hidden sm:inline">Por página</span>
                                <select aria-label="Linhas por página" value={size} onChange={(event) => changeSize(Number(event.target.value))} className={`${control} w-20`}>
                                    {[...new Set<number>([...PAGE_SIZES, size])]
                                        .sort((a, b) => a - b)
                                        .map((option) => (
                                            <option key={option} value={option}>
                                                {option}
                                            </option>
                                        ))}
                                </select>
                            </label>

                            <nav aria-label="Páginas" className="flex items-center gap-1">
                                <button type="button" className={button} disabled={info.page <= 1} onClick={() => goTo(info.page - 1)} aria-label="Página anterior">
                                    ‹
                                </button>
                                <span className="px-2 text-xs sm:hidden">
                                    {info.page} / {info.pages}
                                </span>
                                <span className="hidden items-center gap-1 sm:flex">
                                    {pageWindow(info.page, info.pages).map((item, index) =>
                                        item === '…' ? (
                                            <span key={`gap-${index}`} className="px-1 text-gray-400">
                                                …
                                            </span>
                                        ) : (
                                            <button
                                                key={item}
                                                type="button"
                                                aria-current={item === info.page ? 'page' : undefined}
                                                aria-label={`Página ${item}`}
                                                className={`${button} ${item === info.page ? '!bg-emerald-600 !text-white !ring-emerald-600' : ''}`}
                                                onClick={() => goTo(item)}
                                            >
                                                {item}
                                            </button>
                                        ),
                                    )}
                                </span>
                                <button type="button" className={button} disabled={info.page >= info.pages} onClick={() => goTo(info.page + 1)} aria-label="Próxima página">
                                    ›
                                </button>
                            </nav>
                        </div>
                    )}
                </footer>
            )}
        </section>
    );
}

/** Monta a configuração do modo servidor a partir do que o controlador devolve (paginador + `TableQuery::props`). */
export function serverTable(
    url: string,
    paginator: Paginated<unknown>,
    filters: TableFilters,
    defaults: { sort: string; dir?: SortDir; per_page?: number },
    only: string[],
): ServerConfig {
    return {
        url,
        paginator,
        state: { q: filters.q, sort: filters.sort, dir: filters.dir, per_page: filters.per_page, filters: filters.values ?? {} },
        defaults: { per_page: defaults.per_page ?? 15, sort: defaults.sort, dir: defaults.dir ?? 'asc' },
        only,
    };
}

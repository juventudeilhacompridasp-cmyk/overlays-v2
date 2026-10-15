/**
 * Layout editável do Modo operador (código puro, testado em Node): grade de COLS x ROWS células, blocos que se movem e
 * redimensionam com encaixe, empurrando os vizinhos para baixo. A tela só desenha e guarda; as regras ficam aqui.
 */
export const COLS = 12;
export const ROWS = 16;

export type PanelId = 'pgm' | 'pvw' | 'live' | 'screens' | 'championship' | 'lineup' | 'notices' | 'cartelas' | 'clock' | 'stats' | 'summary' | 'custom' | 'obs';

export type Item = { id: PanelId; x: number; y: number; w: number; h: number };
export type Layout = Item[];

/** Metadados dos blocos: título, tamanho mínimo e tamanho ao reaparecer. */
export const PANELS: { id: PanelId; title: string; minW: number; minH: number; w: number; h: number }[] = [
    { id: 'pgm', title: 'PGM (no ar)', minW: 3, minH: 3, w: 4, h: 5 },
    { id: 'pvw', title: 'PVW (prévia)', minW: 3, minH: 3, w: 4, h: 5 },
    { id: 'live', title: 'No ar agora', minW: 2, minH: 3, w: 3, h: 5 },
    { id: 'screens', title: 'Telas extras', minW: 2, minH: 3, w: 3, h: 5 },
    { id: 'championship', title: 'Campeonato', minW: 2, minH: 2, w: 3, h: 4 },
    { id: 'lineup', title: 'Escalação', minW: 3, minH: 4, w: 5, h: 5 },
    { id: 'notices', title: 'Lances e avisos', minW: 3, minH: 3, w: 5, h: 5 },
    { id: 'cartelas', title: 'Cartelas de transmissão', minW: 3, minH: 2, w: 5, h: 2 },
    { id: 'clock', title: 'Acréscimos no relógio', minW: 3, minH: 2, w: 4, h: 3 },
    { id: 'stats', title: 'Estatísticas da partida', minW: 3, minH: 3, w: 4, h: 7 },
    { id: 'summary', title: 'Resumo', minW: 2, minH: 2, w: 4, h: 2 },
    { id: 'custom', title: 'Overlays livres', minW: 3, minH: 2, w: 5, h: 3 },
    { id: 'obs', title: 'OBS (saída real)', minW: 3, minH: 4, w: 4, h: 6 },
];

const META = new Map(PANELS.map((panel) => [panel.id, panel]));
const clamp = (value: number, min: number, max: number) => Math.max(min, Math.min(max, value));

export const overlaps = (a: Item, b: Item) => a.id !== b.id && a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;

/** Todos dentro da grade, acima do tamanho mínimo e sem sobreposição. */
export function isValid(layout: Layout): boolean {
    const seen = new Set<string>();

    for (const item of layout) {
        const meta = META.get(item.id);

        if (!meta || seen.has(item.id)) return false;
        seen.add(item.id);
        if (![item.x, item.y, item.w, item.h].every(Number.isInteger)) return false;
        if (item.w < meta.minW || item.h < meta.minH || item.x < 0 || item.y < 0 || item.x + item.w > COLS || item.y + item.h > ROWS) return false;
    }

    return layout.every((item, index) => layout.slice(index + 1).every((other) => !overlaps(item, other)));
}

/** Empurra para baixo quem bate no item `fixed`; devolve null se algo sair da grade. */
function resolve(layout: Layout, fixedId: PanelId): Layout | null {
    const items = layout.map((item) => ({ ...item }));
    const queue: PanelId[] = [fixedId];
    let guard = 0;

    while (queue.length > 0) {
        if (++guard > 500) return null;

        const currentId = queue.shift();
        const current = items.find((item) => item.id === currentId)!;

        for (const other of items) {
            if (!overlaps(current, other)) continue;

            other.y = current.y + current.h;
            if (other.y + other.h > ROWS) return null;
            queue.push(other.id);
        }
    }

    return items;
}

/** Move o bloco para (x, y) em células; null quando não cabe (a tela mantém a última posição válida). */
export function moveTo(layout: Layout, id: PanelId, x: number, y: number): Layout | null {
    const item = layout.find((entry) => entry.id === id);

    if (!item) return null;

    const next = layout.map((entry) => (entry.id === id ? { ...entry, x: clamp(Math.round(x), 0, COLS - item.w), y: clamp(Math.round(y), 0, ROWS - item.h) } : entry));

    return resolve(next, id) ?? swap(next, id, item);
}

const inside = (item: Item) => item.x >= 0 && item.y >= 0 && item.x + item.w <= COLS && item.y + item.h <= ROWS;
const free = (layout: Layout) => layout.every((item, index) => inside(item) && layout.slice(index + 1).every((other) => !overlaps(item, other)));

/** Quando empurrar não cabe, troca de lugar com o bloco mais atingido (ele vai para onde este estava). */
function swap(layout: Layout, id: PanelId, origin: Item): Layout | null {
    const moved = layout.find((item) => item.id === id)!;
    const area = (other: Item) => Math.max(0, Math.min(moved.x + moved.w, other.x + other.w) - Math.max(moved.x, other.x)) * Math.max(0, Math.min(moved.y + moved.h, other.y + other.h) - Math.max(moved.y, other.y));
    const hit = layout.filter((other) => overlaps(moved, other)).sort((a, b) => area(b) - area(a))[0];

    if (!hit) return null;

    const placed = layout.map((item) => (item.id === hit.id ? { ...item, x: clamp(origin.x, 0, COLS - item.w), y: clamp(origin.y, 0, ROWS - item.h) } : item));

    return free(placed) ? placed : null;
}

/** Muda o tamanho respeitando o mínimo do bloco e a grade. */
export function resizeTo(layout: Layout, id: PanelId, w: number, h: number): Layout | null {
    const item = layout.find((entry) => entry.id === id);
    const meta = META.get(id);

    if (!item || !meta) return null;

    const next = layout.map((entry) => (entry.id === id ? { ...entry, w: clamp(Math.round(w), meta.minW, COLS - item.x), h: clamp(Math.round(h), meta.minH, ROWS - item.y) } : entry));

    return resolve(next, id);
}

/** Primeiro lugar livre (de cima para a esquerda) para o tamanho pedido, ou null. */
export function firstFree(layout: Layout, w: number, h: number): { x: number; y: number } | null {
    for (let y = 0; y + h <= ROWS; y++) {
        for (let x = 0; x + w <= COLS; x++) {
            // Id que não é de nenhum bloco: `overlaps` ignora quem tem o mesmo id.
            const probe = { id: '' as PanelId, x, y, w, h };

            if (layout.every((item) => !overlaps(probe, item))) return { x, y };
        }
    }

    return null;
}

/** Faz um bloco ocultado voltar; encolhe até o mínimo se o tamanho padrão não couber. Null = sem espaço. */
export function showPanel(layout: Layout, id: PanelId): Layout | null {
    const meta = META.get(id);

    if (!meta || layout.some((item) => item.id === id)) return layout;

    for (const [w, h] of [[meta.w, meta.h], [meta.minW, meta.h], [meta.w, meta.minH], [meta.minW, meta.minH]]) {
        const spot = firstFree(layout, w, h);

        if (spot) return [...layout, { id, ...spot, w, h }];
    }

    return null;
}

export const hidePanel = (layout: Layout, id: PanelId): Layout => layout.filter((item) => item.id !== id);

/** Modelos prontos. O padrão repete o desenho original do modo operador (3 colunas). */
export const PRESETS: { key: string; label: string; layout: Layout }[] = [
    {
        key: 'default', label: 'Padrão',
        layout: [
            { id: 'live', x: 0, y: 0, w: 3, h: 5 }, { id: 'screens', x: 0, y: 5, w: 3, h: 5 }, { id: 'championship', x: 0, y: 10, w: 3, h: 4 }, { id: 'summary', x: 0, y: 14, w: 3, h: 2 },
            { id: 'lineup', x: 3, y: 0, w: 5, h: 5 }, { id: 'notices', x: 3, y: 5, w: 5, h: 5 }, { id: 'cartelas', x: 3, y: 10, w: 5, h: 3 }, { id: 'custom', x: 3, y: 13, w: 5, h: 3 },
            { id: 'pgm', x: 8, y: 0, w: 4, h: 4 }, { id: 'pvw', x: 8, y: 4, w: 4, h: 4 }, { id: 'clock', x: 8, y: 8, w: 4, h: 3 }, { id: 'stats', x: 8, y: 11, w: 4, h: 5 },
        ],
    },
    {
        key: 'lineup', label: 'Escalação em foco',
        layout: [
            { id: 'lineup', x: 0, y: 0, w: 7, h: 5 }, { id: 'notices', x: 0, y: 5, w: 7, h: 5 },
            { id: 'cartelas', x: 0, y: 10, w: 7, h: 2 }, { id: 'custom', x: 0, y: 12, w: 7, h: 2 }, { id: 'summary', x: 0, y: 14, w: 7, h: 2 },
            { id: 'pgm', x: 7, y: 0, w: 5, h: 4 }, { id: 'pvw', x: 7, y: 4, w: 5, h: 4 }, { id: 'live', x: 7, y: 8, w: 5, h: 4 },
            { id: 'screens', x: 7, y: 12, w: 5, h: 4 },
        ],
    },
    {
        key: 'game', label: 'Jogo rolando',
        layout: [
            { id: 'live', x: 0, y: 0, w: 4, h: 6 }, { id: 'stats', x: 4, y: 0, w: 5, h: 9 }, { id: 'clock', x: 9, y: 0, w: 3, h: 3 },
            { id: 'notices', x: 9, y: 3, w: 3, h: 6 }, { id: 'screens', x: 0, y: 6, w: 4, h: 5 }, { id: 'summary', x: 4, y: 9, w: 5, h: 2 },
            { id: 'lineup', x: 4, y: 11, w: 5, h: 5 }, { id: 'cartelas', x: 9, y: 9, w: 3, h: 3 }, { id: 'pgm', x: 0, y: 11, w: 4, h: 5 }, { id: 'pvw', x: 9, y: 12, w: 3, h: 4 },
        ],
    },
];

export const defaultLayout = (): Layout => PRESETS[0].layout.map((item) => ({ ...item }));

/** Lê o que foi guardado: ignora blocos desconhecidos/inválidos e cai no padrão se o resultado não for um layout válido. */
export function sanitizeLayout(raw: unknown): Layout {
    if (!Array.isArray(raw)) return defaultLayout();

    const items: Layout = [];

    for (const entry of raw) {
        const value = entry as Partial<Item> | null;
        const meta = META.get(value?.id as PanelId);

        if (!meta || items.some((item) => item.id === meta.id) || !value) continue;

        const w = clamp(Math.round(Number(value.w)), meta.minW, COLS);
        const h = clamp(Math.round(Number(value.h)), meta.minH, ROWS);
        const x = clamp(Math.round(Number(value.x)), 0, COLS - w);
        const y = clamp(Math.round(Number(value.y)), 0, ROWS - h);

        if ([w, h, x, y].every(Number.isFinite)) items.push({ id: meta.id, x, y, w, h });
    }

    return items.length > 0 && isValid(items) ? items : defaultLayout();
}

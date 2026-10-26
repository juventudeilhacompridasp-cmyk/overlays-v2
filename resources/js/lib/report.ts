/**
 * Relatórios ilustrados (módulo Reports, ADR 0015): tipos do documento de blocos que o servidor monta
 * (`GameReport`/`ChampionshipReport`) e regras puras das telas — o slide no ar (espelho de
 * `OverlayReportSettings::slideAt`), a geometria dos gráficos e o corte para caber no overlay. Testado em Node.
 */

export interface ReportKpi { icon: string; label: string; value: string; hint?: string | null; color?: string | null; progress?: number | null }
export interface ReportSide { name: string; color: string }

export type ReportBlock =
    | { type: 'kpis'; items: ReportKpi[] }
    | { type: 'versus'; title: string | null; home: ReportSide; away: ReportSide; rows: { label: string; home: number; away: number; suffix?: string }[] }
    | { type: 'columns'; title: string; unit: string; series: { label: string; color: string }[]; categories: { label: string; values: number[] }[] }
    | { type: 'donut'; title: string; total: number; total_label: string; parts: { label: string; value: number; color: string }[] }
    | { type: 'ranking'; title: string; unit: string; items: { position: number; label: string; sub: string | null; value: number; color: string | null }[] }
    | { type: 'table'; title: string | null; columns: { key: string; label: string; align: 'left' | 'center' | 'right'; width: number }[]; rows: { cells: Record<string, string>; highlight: boolean; color: string | null }[] }
    | { type: 'timeline'; items: { minute: string | null; period: string | null; side: 'home' | 'away' | null; icon: string; color: string; title: string; detail: string | null }[] }
    | { type: 'form'; title: string; rows: { label: string; color: string; results: string[] }[] }
    | { type: 'list'; title: string; items: { title: string; detail: string | null; value: string | null; icon: string | null; color: string | null }[] }
    | { type: 'note'; text: string };

export interface ReportSection { key: string; title: string; icon: string; blocks: ReportBlock[] }

export interface ReportHeroSide { name: string; short: string; color: string; score: number; logo_url: string | null }

export interface ReportDocument {
    kind: 'game' | 'championship';
    title: string;
    subtitle: string;
    organization: string;
    accent: string;
    private: boolean;
    generated_at: string;
    meta: { icon: string; label: string; value: string }[];
    hero: { home: ReportHeroSide; away: ReportHeroSide; status: string; unit: string; penalties: string | null; sets: string | null } | null;
    sections: ReportSection[];
    icons: Record<string, string>;
}

export type ReportStyle = 'broadcast' | 'glass' | 'light';
export type ReportAnimation = 'rise' | 'slide' | 'zoom' | 'fade';

export interface ReportTimer { mode: 'manual' | 'auto'; seconds: number; step: number; anchor_ms: number }

export interface ReportOverlayState extends ReportTimer {
    style: ReportStyle;
    animation: ReportAnimation;
    scale: number;
    count: number;
    document: ReportDocument;
}

export const REPORT_STYLES: { key: ReportStyle; label: string }[] = [
    { key: 'broadcast', label: 'Transmissão (escuro)' },
    { key: 'glass', label: 'Vidro (translúcido)' },
    { key: 'light', label: 'Claro (papel)' },
];

export const REPORT_ANIMATIONS: { key: ReportAnimation; label: string }[] = [
    { key: 'rise', label: 'Subir' },
    { key: 'slide', label: 'Deslizar' },
    { key: 'zoom', label: 'Zoom' },
    { key: 'fade', label: 'Esmaecer' },
];

/** Slide na tela agora: manual fica na etapa; automático avança a cada `seconds` desde `anchor_ms`. */
export function reportSlideAt(timer: ReportTimer, count: number, nowMs: number): number {
    if (count <= 0) return 0;

    let step = Math.max(0, Math.floor(timer.step));

    if (timer.mode === 'auto') step += Math.floor(Math.max(0, nowMs - timer.anchor_ms) / (Math.max(1, timer.seconds) * 1000));

    return step % count;
}

/** Quanto falta para o próximo slide no automático (para a tela agendar a troca); null no manual. */
export function msToNextSlide(timer: ReportTimer, nowMs: number): number | null {
    if (timer.mode !== 'auto') return null;

    const period = Math.max(1, timer.seconds) * 1000;

    return period - (Math.max(0, nowMs - timer.anchor_ms) % period);
}

/** Teto "redondo" do eixo — o mesmo de `ReportPdf::niceMax`. */
export function niceMax(value: number): number {
    if (!(value > 0)) return 1;
    if (value <= 4) return 4; // grade de 1 em 1

    const power = 10 ** Math.floor(Math.log10(value));

    for (const step of [1, 2, 2.5, 5, 10]) {
        if (value <= step * power) return Math.max(4, step * power);
    }

    return 10 * power;
}

/** Fração (0 a 1) de `value` em relação a `max`. */
export function share(value: number, max: number): number {
    return max > 0 ? Math.max(0, Math.min(1, value / max)) : 0;
}

/** Fatias da rosca em frações acumuladas (do topo, sentido horário). */
export function donutSegments(parts: { value: number; color: string }[]): { from: number; to: number; color: string }[] {
    const total = parts.reduce((sum, part) => sum + Math.max(0, part.value), 0);
    let from = 0;

    return total <= 0 ? [] : parts.filter((part) => part.value > 0).map((part) => {
        const to = from + part.value / total;
        const segment = { from, to, color: part.color };
        from = to;

        return segment;
    });
}

/** Caminho SVG de uma fatia de anel (raio externo `r`, interno `inner`), de `from` a `to` (frações). */
export function arcPath(cx: number, cy: number, r: number, inner: number, from: number, to: number): string {
    const span = Math.min(0.9999, Math.max(0, to - from));
    const point = (t: number, radius: number) => {
        const angle = t * 2 * Math.PI;

        return `${(cx + radius * Math.sin(angle)).toFixed(2)} ${(cy - radius * Math.cos(angle)).toFixed(2)}`;
    };
    const end = from + span;
    const large = span > 0.5 ? 1 : 0;

    return `M${point(from, r)}A${r} ${r} 0 ${large} 1 ${point(end, r)}L${point(end, inner)}A${inner} ${inner} 0 ${large} 0 ${point(from, inner)}Z`;
}

/** Cor da bolinha da forma recente. */
export function formColor(result: string): string {
    return result === 'V' ? '#16a34a' : result === 'D' ? '#dc2626' : '#94a3b8';
}

/** Número curto pt-BR: inteiro sem casas, fração com uma casa. */
export function shortNumber(value: number): string {
    return Number.isInteger(value) ? value.toLocaleString('pt-BR') : value.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

/** Limites de cada bloco para caber num slide 1920 x 1080 (o PDF mostra tudo; o overlay corta). */
export const OVERLAY_LIMITS = { table: 10, timeline: 8, ranking: 8, list: 5, form: 8, columns: 12, kpis: 4 } as const;

/** O bloco cortado para o overlay: tabelas e listas ficam com as primeiras linhas, a linha do tempo com os últimos lances. */
export function compactBlock(block: ReportBlock): ReportBlock {
    switch (block.type) {
        case 'table':
            return { ...block, rows: block.rows.slice(0, OVERLAY_LIMITS.table) };
        case 'timeline':
            return { ...block, items: block.items.slice(-OVERLAY_LIMITS.timeline) };
        case 'ranking':
            return { ...block, items: block.items.slice(0, OVERLAY_LIMITS.ranking) };
        case 'list':
            return { ...block, items: block.items.slice(0, OVERLAY_LIMITS.list) };
        case 'form':
            return { ...block, rows: block.rows.slice(0, OVERLAY_LIMITS.form) };
        case 'columns':
            return { ...block, categories: block.categories.slice(0, OVERLAY_LIMITS.columns) };
        case 'kpis':
            return { ...block, items: block.items.slice(0, OVERLAY_LIMITS.kpis) };
        default:
            return block;
    }
}

/** Blocos que ocupam a largura inteira do slide; os demais ficam lado a lado quando há mais de um. */
export function isWideBlock(block: ReportBlock): boolean {
    return block.type === 'kpis' || block.type === 'versus' || block.type === 'columns' || block.type === 'timeline';
}

/** Endereço do PDF com as seções escolhidas (vazio = todas). */
export function sectionsQuery(sections: string[]): string {
    return sections.length === 0 ? '' : `?secoes=${sections.map(encodeURIComponent).join(',')}`;
}

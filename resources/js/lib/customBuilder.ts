/**
 * Overlay livre (Builder): tipos, textos com dados ao vivo, animações e modelos prontos. Código puro (testado em Node):
 * a página do OBS e o editor do painel só desenham o que sai daqui. Posição e tamanho dos elementos em % do canvas.
 */
import type { CSSProperties } from 'react';

export type ElementType = 'text' | 'image' | 'shape';
export type BuilderAnimation = 'none' | 'fade' | 'slide-left' | 'slide-right' | 'slide-up' | 'slide-down' | 'zoom' | 'pop' | 'wipe' | 'flip';

export interface BuilderElement {
    id: string;
    type: ElementType;
    name: string;
    x: number;
    y: number;
    w: number;
    h: number;
    rotation: number;
    opacity: number;
    visible: boolean;
    locked: boolean;
    text: string;
    font: string;
    size: number;
    weight: number;
    color: string;
    align: 'left' | 'center' | 'right';
    valign: 'top' | 'middle' | 'bottom';
    transform: 'none' | 'uppercase';
    spacing: number;
    lineHeight: number;
    italic: boolean;
    marquee: boolean;
    marqueeSpeed: number;
    fill: string;
    fill2: string;
    fillAngle: number;
    fillOpacity: number;
    radius: number;
    borderColor: string;
    borderWidth: number;
    shadow: 'none' | 'soft' | 'strong';
    padding: number;
    src: string;
    fit: 'cover' | 'contain';
    shape: 'rect' | 'circle';
    animIn: BuilderAnimation;
    animOut: BuilderAnimation | 'same';
    delay: number;
    duration: number;
}

export interface BuilderItem {
    id: string;
    name: string;
    width: number;
    height: number;
    elements: BuilderElement[];
}

export const ANIMATIONS: { key: BuilderAnimation; label: string }[] = [
    { key: 'none', label: 'Sem animação' },
    { key: 'fade', label: 'Fade' },
    { key: 'slide-left', label: 'Deslizar da esquerda' },
    { key: 'slide-right', label: 'Deslizar da direita' },
    { key: 'slide-up', label: 'Subir' },
    { key: 'slide-down', label: 'Descer' },
    { key: 'zoom', label: 'Zoom' },
    { key: 'pop', label: 'Pop com ressalto' },
    { key: 'wipe', label: 'Cortina' },
    { key: 'flip', label: 'Virar' },
];

export const CANVAS_SIZES: { width: number; height: number; label: string }[] = [
    { width: 1920, height: 1080, label: 'Tela cheia 1920 × 1080' },
    { width: 1280, height: 720, label: 'Tela cheia 1280 × 720' },
    { width: 1080, height: 1920, label: 'Vertical 1080 × 1920' },
    { width: 1500, height: 200, label: 'Barra 1500 × 200' },
    { width: 1200, height: 260, label: 'Lower third 1200 × 260' },
    { width: 800, height: 450, label: 'Cartão 800 × 450' },
    { width: 600, height: 600, label: 'Quadrado 600 × 600' },
];

export const TEXT_TOKENS: { token: string; label: string }[] = [
    { token: '{home.name}', label: 'Mandante' },
    { token: '{home.short}', label: 'Sigla mandante' },
    { token: '{home.score}', label: 'Placar mandante' },
    { token: '{away.name}', label: 'Visitante' },
    { token: '{away.short}', label: 'Sigla visitante' },
    { token: '{away.score}', label: 'Placar visitante' },
    { token: '{clock}', label: 'Cronômetro' },
    { token: '{period}', label: 'Período' },
    { token: '{competition}', label: 'Competição' },
    { token: '{division}', label: 'Divisão' },
    { token: '{stage}', label: 'Fase' },
    { token: '{venue}', label: 'Local' },
    { token: '{sponsor}', label: 'Patrocinador' },
    { token: '{time}', label: 'Hora' },
    { token: '{table.1.name}', label: 'Tabela: 1º colocado' },
    { token: '{table.1.points}', label: 'Tabela: pontos do 1º' },
];

export const IMAGE_TOKENS: { token: string; label: string }[] = [
    { token: 'token:home.logo', label: 'Escudo do mandante' },
    { token: 'token:away.logo', label: 'Escudo do visitante' },
    { token: 'token:sponsor', label: 'Logo do patrocinador no ar' },
];

export interface DataContext {
    home: { name: string; short: string; score: number | string; logo: string | null } | null;
    away: { name: string; short: string; score: number | string; logo: string | null } | null;
    clock: string;
    period: string;
    competition: string;
    division: string;
    stage: string;
    venue: string;
    sponsor: { name: string; logo: string | null } | null;
    time: string;
    table?: { position: number; team: string; points: number; played: number; won: number; drawn: number; lost: number; goal_difference: number }[] | null;
}

export const TABLE_FIELDS = ['pos', 'name', 'points', 'played', 'won', 'drawn', 'lost', 'gd'] as const;

/** Valor de `{table.N.campo}` (N de 1 a 10); campo desconhecido devolve null (o texto fica como escrito) e linha sem dado, vazio. */
export function tableValue(table: DataContext['table'], index: number, field: string): string | null {
    if (!(TABLE_FIELDS as readonly string[]).includes(field) || index < 1 || index > 10) return null;

    const row = table?.[index - 1];

    if (!row) return '';

    switch (field) {
        case 'pos': return String(row.position);
        case 'name': return row.team;
        case 'points': return String(row.points);
        case 'played': return String(row.played);
        case 'won': return String(row.won);
        case 'drawn': return String(row.drawn);
        case 'lost': return String(row.lost);
        default: return String(row.goal_difference);
    }
}

/** Troca os `{dados}` do texto pelos valores do jogo no ar; sem dado, fica vazio (nunca "undefined"). */
export function resolveText(text: string, ctx: DataContext): string {
    const values: Record<string, string> = {
        'home.name': ctx.home?.name ?? '', 'home.short': ctx.home?.short ?? '', 'home.score': String(ctx.home?.score ?? ''),
        'away.name': ctx.away?.name ?? '', 'away.short': ctx.away?.short ?? '', 'away.score': String(ctx.away?.score ?? ''),
        clock: ctx.clock, period: ctx.period, competition: ctx.competition, division: ctx.division, stage: ctx.stage, venue: ctx.venue,
        sponsor: ctx.sponsor?.name ?? '', time: ctx.time,
    };

    return text.replace(/\{([a-z]+(?:\.[a-z0-9]+)*)\}/g, (match, key: string) => {
        const row = /^table\.(\d{1,2})\.([a-z]+)$/.exec(key);

        if (row) return tableValue(ctx.table ?? null, Number(row[1]), row[2]) ?? match;

        return key in values ? values[key] : match;
    });
}

/** Endereço da imagem do elemento: `token:` vira o escudo/patrocinador do jogo; vazio quando não há. */
export function resolveImage(src: string, ctx: DataContext): string | null {
    if (src === 'token:home.logo') return ctx.home?.logo ?? null;
    if (src === 'token:away.logo') return ctx.away?.logo ?? null;
    if (src === 'token:sponsor') return ctx.sponsor?.logo ?? null;

    return src.startsWith('https://') ? src : null;
}

/** Quadros da animação de ENTRADA (do escondido ao mostrado); a saída toca os mesmos quadros ao contrário. */
export function animationFrames(animation: BuilderAnimation): Keyframe[] {
    switch (animation) {
        case 'fade': return [{ opacity: 0 }, { opacity: 1 }];
        case 'slide-left': return [{ opacity: 0, transform: 'translateX(-60%)' }, { opacity: 1, transform: 'none' }];
        case 'slide-right': return [{ opacity: 0, transform: 'translateX(60%)' }, { opacity: 1, transform: 'none' }];
        case 'slide-up': return [{ opacity: 0, transform: 'translateY(60%)' }, { opacity: 1, transform: 'none' }];
        case 'slide-down': return [{ opacity: 0, transform: 'translateY(-60%)' }, { opacity: 1, transform: 'none' }];
        case 'zoom': return [{ opacity: 0, transform: 'scale(0.6)' }, { opacity: 1, transform: 'none' }];
        case 'pop': return [{ opacity: 0, transform: 'scale(0.4)' }, { opacity: 1, transform: 'scale(1.12)', offset: 0.65 }, { opacity: 1, transform: 'scale(0.97)', offset: 0.85 }, { opacity: 1, transform: 'none' }];
        case 'wipe': return [{ clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0 0 0)' }];
        case 'flip': return [{ opacity: 0, transform: 'perspective(800px) rotateY(-90deg)' }, { opacity: 1, transform: 'none' }];
        default: return [];
    }
}

/** Qual animação toca na saída: a própria ou a de entrada ao contrário. */
export function exitAnimation(element: Pick<BuilderElement, 'animIn' | 'animOut'>): BuilderAnimation {
    return element.animOut === 'same' ? element.animIn : element.animOut;
}

/** Tempo (ms) até o último elemento terminar de entrar ou sair. */
export function totalDuration(elements: Pick<BuilderElement, 'delay' | 'duration' | 'visible'>[]): number {
    return elements.filter((element) => element.visible).reduce((max, element) => Math.max(max, element.delay + element.duration), 0);
}

const SHADOWS = { none: 'none', soft: '0 6px 18px rgba(0,0,0,.35)', strong: '0 12px 34px rgba(0,0,0,.7)' };

/** Caixa do elemento no canvas: posição em %, rotação, opacidade, preenchimento/degradê, borda e sombra. */
export function elementBox(element: BuilderElement): CSSProperties {
    const fill = element.fill === '' ? null : withAlpha(element.fill, element.fillOpacity);
    const fill2 = element.fill2 === '' ? null : withAlpha(element.fill2, element.fillOpacity);
    const background = fill && fill2 ? `linear-gradient(${element.fillAngle}deg, ${fill}, ${fill2})` : fill ?? undefined;

    return {
        position: 'absolute', left: `${element.x}%`, top: `${element.y}%`, width: `${element.w}%`, height: `${element.h}%`,
        transform: element.rotation === 0 ? undefined : `rotate(${element.rotation}deg)`, opacity: element.opacity / 100,
        background, borderRadius: element.shape === 'circle' ? '50%' : `${element.radius}px`,
        border: element.borderWidth > 0 ? `${element.borderWidth}px solid ${element.borderColor}` : undefined,
        boxShadow: SHADOWS[element.shadow], boxSizing: 'border-box', overflow: 'hidden',
    };
}

/** Estilo do texto dentro da caixa. A fonte `global` herda a do canal. */
export function textStyle(element: BuilderElement, fontFamily: string | undefined): CSSProperties {
    const justify = { top: 'flex-start', middle: 'center', bottom: 'flex-end' }[element.valign];

    return {
        display: 'flex', flexDirection: 'column', justifyContent: justify, width: '100%', height: '100%', padding: element.padding,
        textAlign: element.align, color: element.color, fontSize: element.size, fontWeight: element.weight, fontStyle: element.italic ? 'italic' : 'normal',
        textTransform: element.transform === 'uppercase' ? 'uppercase' : 'none', letterSpacing: `${element.spacing / 10}em`, lineHeight: element.lineHeight / 100,
        fontFamily, whiteSpace: element.marquee ? 'nowrap' : 'pre-wrap', boxSizing: 'border-box',
    };
}

function withAlpha(hex: string, percent: number): string {
    const value = Math.max(0, Math.min(100, percent));

    return value >= 100 ? hex : `${hex}${Math.round((value / 100) * 255).toString(16).padStart(2, '0')}`;
}

let counter = 0;

/** Elemento novo com os padrões; `n` só serve para o ID e o nome únicos. */
export function newElement(type: ElementType, taken: string[] = []): BuilderElement {
    let id: string;
    do {
        counter += 1;
        id = `el-${Date.now().toString(36).slice(-4)}${counter}`;
    } while (taken.includes(id));

    const names = { text: 'Texto', image: 'Imagem', shape: 'Forma' };
    const base: BuilderElement = {
        id, type, name: names[type], x: 10, y: 10, w: 30, h: 12, rotation: 0, opacity: 100, visible: true, locked: false,
        text: type === 'text' ? 'Texto' : '', font: 'global', size: 48, weight: 700, color: '#ffffff', align: 'left', valign: 'middle', transform: 'none',
        spacing: 0, lineHeight: 110, italic: false, marquee: false, marqueeSpeed: 14, fill: '', fill2: '', fillAngle: 135, fillOpacity: 100,
        radius: 0, borderColor: '#ffffff', borderWidth: 0, shadow: 'none', padding: 0, src: '', fit: 'cover', shape: 'rect', animIn: 'fade', animOut: 'same', delay: 0, duration: 600,
    };

    return type === 'shape' ? { ...base, fill: '#2563eb', h: 20 } : type === 'image' ? { ...base, w: 20, h: 30, fit: 'contain' } : base;
}

/** Move o elemento (deslocamento em % do canvas), sem deixar o canto sair muito da tela. */
export function moveElement(element: BuilderElement, dx: number, dy: number): BuilderElement {
    const clamp = (value: number) => Math.round(Math.max(-100, Math.min(200, value)) * 100) / 100;

    return { ...element, x: clamp(element.x + dx), y: clamp(element.y + dy) };
}

/** Arquivo de exportação (`juventude-overlay`, versão 1). */
export function exportOverlay(item: Pick<BuilderItem, 'name' | 'width' | 'height' | 'elements'>): { format: 'juventude-overlay'; version: 1; overlay: Pick<BuilderItem, 'name' | 'width' | 'height' | 'elements'> } {
    return { format: 'juventude-overlay', version: 1, overlay: { name: item.name, width: item.width, height: item.height, elements: item.elements } };
}

export interface Template {
    key: string;
    name: string;
    caption: string;
    width: number;
    height: number;
    build: () => BuilderElement[];
}

const el = (type: ElementType, patch: Partial<BuilderElement>): BuilderElement => ({ ...newElement(type), ...patch });

export const TEMPLATES: Template[] = [
    { key: 'blank', name: 'Em branco', caption: 'Comece do zero', width: 1920, height: 1080, build: () => [] },
    {
        key: 'lower-third', name: 'Lower third', caption: 'Nome e função', width: 1200, height: 260,
        build: () => [
            el('shape', { id: 'bg', name: 'Fundo', x: 0, y: 0, w: 100, h: 100, fill: '#0f172a', fillOpacity: 94, radius: 16, animIn: 'slide-left' }),
            el('shape', { id: 'accent', name: 'Destaque', x: 0, y: 0, w: 1.5, h: 100, fill: '#14b8a6', animIn: 'slide-left' }),
            el('text', { id: 'title', name: 'Título', x: 5, y: 12, w: 90, h: 46, text: 'NOME DO CONVIDADO', size: 72, weight: 900, transform: 'uppercase', valign: 'bottom', animIn: 'slide-left', delay: 100 }),
            el('text', { id: 'subtitle', name: 'Subtítulo', x: 5, y: 60, w: 90, h: 28, text: 'Função ou descrição', size: 38, weight: 500, opacity: 80, valign: 'top', animIn: 'slide-left', delay: 200 }),
        ],
    },
    {
        key: 'mini-score', name: 'Mini-placar', caption: 'Placar com dados ao vivo', width: 800, height: 450,
        build: () => [
            el('shape', { id: 'bg', name: 'Fundo', x: 0, y: 0, w: 100, h: 22, fill: '#0f172a', fillOpacity: 95, radius: 14, animIn: 'zoom' }),
            el('text', { id: 'home', name: 'Mandante', x: 3, y: 0, w: 32, h: 22, text: '{home.short}', size: 44, weight: 900, align: 'right', animIn: 'fade' }),
            el('text', { id: 'score', name: 'Placar', x: 36, y: 0, w: 28, h: 22, text: '{home.score} × {away.score}', size: 52, weight: 900, align: 'center', color: '#fbbf24', animIn: 'pop' }),
            el('text', { id: 'away', name: 'Visitante', x: 65, y: 0, w: 32, h: 22, text: '{away.short}', size: 44, weight: 900, animIn: 'fade' }),
        ],
    },
    {
        key: 'notice', name: 'Aviso central', caption: 'Mensagem no meio da tela', width: 1920, height: 1080,
        build: () => [
            el('shape', { id: 'bg', name: 'Fundo', x: 20, y: 38, w: 60, h: 24, fill: '#0f172a', fill2: '#1e293b', fillOpacity: 96, radius: 24, shadow: 'strong', animIn: 'zoom' }),
            el('text', { id: 'title', name: 'Mensagem', x: 24, y: 40, w: 52, h: 20, text: 'AVISO IMPORTANTE', size: 84, weight: 900, align: 'center', transform: 'uppercase', animIn: 'pop', delay: 150 }),
        ],
    },
    {
        key: 'interval', name: 'Intervalo', caption: 'Resultado parcial', width: 1920, height: 1080,
        build: () => [
            el('shape', { id: 'bg', name: 'Fundo', x: 0, y: 0, w: 100, h: 100, fill: '#020617', fillOpacity: 88, animIn: 'fade' }),
            el('text', { id: 'title', name: 'Título', x: 10, y: 22, w: 80, h: 12, text: 'INTERVALO', size: 96, weight: 900, align: 'center', animIn: 'slide-down' }),
            el('text', { id: 'score', name: 'Placar', x: 10, y: 40, w: 80, h: 22, text: '{home.name} {home.score} × {away.score} {away.name}', size: 72, weight: 800, align: 'center', animIn: 'zoom', delay: 200 }),
            el('text', { id: 'comp', name: 'Competição', x: 10, y: 68, w: 80, h: 8, text: '{competition}', size: 40, weight: 500, align: 'center', opacity: 70, animIn: 'fade', delay: 400 }),
        ],
    },
    {
        key: 'sponsor-seal', name: 'Selo de patrocinador', caption: 'Logo do patrocinador ativo', width: 600, height: 600,
        build: () => [
            el('shape', { id: 'bg', name: 'Selo', x: 0, y: 0, w: 100, h: 100, fill: '#ffffff', shape: 'circle', shadow: 'soft', animIn: 'pop' }),
            el('image', { id: 'logo', name: 'Logo', x: 15, y: 15, w: 70, h: 70, src: 'token:sponsor', fit: 'contain', animIn: 'fade', delay: 200 }),
        ],
    },
    {
        key: 'ticker', name: 'Faixa rolante', caption: 'Texto correndo na barra', width: 1500, height: 200,
        build: () => [
            el('shape', { id: 'bg', name: 'Fundo', x: 0, y: 0, w: 100, h: 100, fill: '#0f172a', animIn: 'slide-up' }),
            el('text', { id: 'ticker', name: 'Texto rolante', x: 2, y: 0, w: 96, h: 100, text: 'Siga a transmissão e acompanhe todos os jogos • {competition}', size: 80, weight: 800, marquee: true, animIn: 'fade', delay: 200 }),
        ],
    },
];

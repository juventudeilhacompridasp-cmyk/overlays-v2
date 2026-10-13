// Motor de artes das partidas (ADR 0009): CÓDIGO PURO. Recebe estilo + dados + tamanho e devolve a lista de operações de
// desenho; quem desenha (canvas) está em artsDraw.ts. Testado em Node: `npm run test:js`.

export type FontKey = 'sans' | 'display' | 'condensed' | 'serif' | 'mono';

export interface ArtStyle {
    background: { mode: 'solid' | 'gradient'; color: string; color2: string; angle: number; pattern: 'none' | 'stripes' | 'grid' | 'dots'; pattern_opacity: number; shade: number };
    palette: { primary: string; secondary: string; text: string; muted: string; accent: string };
    type: { heading: FontKey; body: FontKey; scale: number; weight: 'normal' | 'bold' | 'black'; uppercase: boolean; spacing: number };
    layout: { variant: 'classic' | 'split' | 'poster'; align: 'center' | 'left'; margin: number; frame: boolean; frame_width: number; frame_color: string; radius: number };
    crest: { show: boolean; size: number; shape: 'none' | 'circle' | 'rounded' | 'badge'; bg: string; use_team_colors: boolean };
    elements: { competition: boolean; phase: boolean; date: boolean; time: boolean; venue: boolean; status: boolean; org_logo: boolean; footer: boolean };
    text: { headline: string; footer: string; hashtag: string };
}

export interface ArtSide {
    name: string;
    short: string | null;
    crest: string | null;
    color: string | null;
    score: number | null;
    penalties: number | null;
}

export interface ArtPlayer {
    shirt: number | null;
    name: string;
    position: string | null;
    captain: boolean;
}

export interface ArtLineupSide {
    formation: string | null;
    starters: ArtPlayer[];
    bench: ArtPlayer[];
    coach: string | null;
}

export interface ArtData {
    kind: 'preview' | 'result' | 'lineup';
    competition: string;
    season: string;
    phase: string;
    group: string | null;
    round: number | null;
    leg: number | null;
    date: string | null;
    venue: string | null;
    status: string;
    status_label: string;
    decided: boolean;
    unit: string | null;
    home: ArtSide;
    away: ArtSide;
    org: { name: string | null; logo: string | null };
    lineup: { home: ArtLineupSide; away: ArtLineupSide } | null;
}

export type Align = 'left' | 'center' | 'right';

export type Op =
    | { t: 'background' }
    | { t: 'rect'; x: number; y: number; w: number; h: number; fill: string; alpha?: number; radius?: number }
    | { t: 'frame'; x: number; y: number; w: number; h: number; color: string; width: number; radius: number }
    | { t: 'text'; text: string; x: number; y: number; size: number; color: string; font: 'heading' | 'body'; align: Align; maxW: number; upper?: boolean; weight?: 'normal' | 'bold' | 'black'; alpha?: number }
    | { t: 'image'; src: string; x: number; y: number; w: number; h: number }
    | { t: 'shape'; shape: 'circle' | 'rounded' | 'badge'; x: number; y: number; w: number; h: number; fill: string; alpha?: number };

const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
const WEEKDAYS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

/** "2026-10-14T16:00" → "quarta, 14 out" (sem fuso: a data é a do cadastro). */
export function dateLabel(date: string | null): string | null {
    const match = date?.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) return null;

    const [, year, month, day] = match.map(Number);
    const weekday = new Date(Date.UTC(year, month - 1, day)).getUTCDay();

    return `${WEEKDAYS[weekday]}, ${day} ${MONTHS[month - 1]}`;
}

/** "2026-10-14T16:00" → "16h" ou "16h30". */
export function timeLabel(date: string | null): string | null {
    const match = date?.match(/T(\d{2}):(\d{2})/);

    if (!match) return null;

    return match[2] === '00' ? `${Number(match[1])}h` : `${Number(match[1])}h${match[2]}`;
}

/** "Fase de grupos · Grupo A · Rodada 3 · Ida". */
export function phaseLabel(data: Pick<ArtData, 'phase' | 'group' | 'round' | 'leg'>): string {
    const parts = [data.phase, data.group, data.round ? `Rodada ${data.round}` : null, data.leg ? (data.leg === 1 ? 'Ida' : data.leg === 2 ? 'Volta' : `Jogo ${data.leg}`) : null];

    return parts.filter((part): part is string => Boolean(part)).join(' · ');
}

/** Quebra aproximada por largura (sem medir fonte): serve para listas de reservas. */
export function wrapWords(words: string[], maxChars: number): string[] {
    const lines: string[] = [];
    let current = '';

    for (const word of words) {
        if (current !== '' && (current + ', ' + word).length > maxChars) {
            lines.push(current);
            current = word;
        } else {
            current = current === '' ? word : `${current}, ${word}`;
        }
    }

    if (current !== '') lines.push(current);

    return lines;
}

function initials(name: string): string {
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

/** Monta a arte. Tudo em pixels do tamanho pedido; `u` = 1% do lado menor. */
export function buildArt(style: ArtStyle, data: ArtData, W: number, H: number): Op[] {
    const ops: Op[] = [{ t: 'background' }];
    const u = Math.min(W, H) / 100;
    const S = style.type.scale;
    const m = style.layout.margin * u;
    const x0 = m;
    const y0 = m;
    const CW = W - 2 * m;
    const CH = H - 2 * m;
    const left = style.layout.align === 'left';
    const anchorX = left ? x0 : x0 + CW / 2;
    const align: Align = left ? 'left' : 'center';
    const p = style.palette;

    const text = (value: string, x: number, y: number, size: number, color: string, font: 'heading' | 'body', opts: { align?: Align; maxW?: number; upper?: boolean; weight?: 'normal' | 'bold' | 'black'; alpha?: number } = {}) => {
        if (value === '') return;
        ops.push({ t: 'text', text: value, x, y, size, color, font, align: opts.align ?? align, maxW: opts.maxW ?? CW, upper: opts.upper, weight: opts.weight, alpha: opts.alpha });
    };

    const homeColor = style.crest.use_team_colors && data.home.color ? data.home.color : p.primary;
    const awayColor = style.crest.use_team_colors && data.away.color ? data.away.color : p.secondary;
    const variant = style.layout.variant;

    // Formato muito alto (story): as equipes se empilham em vez de ficarem lado a lado.
    const stacked = data.kind !== 'lineup' && H / W > 1.5;
    const split = variant === 'split' && data.kind !== 'lineup';
    // Texto "suave": sobre as metades coloridas usa o texto principal com transparência (contraste).
    const soft = split ? p.text : p.muted;
    const softAlpha = split ? 0.78 : undefined;

    if (split) {
        ops.push(stacked ? { t: 'rect', x: 0, y: 0, w: W, h: H / 2, fill: homeColor, alpha: 0.92 } : { t: 'rect', x: 0, y: 0, w: W / 2, h: H, fill: homeColor, alpha: 0.92 });
        ops.push(stacked ? { t: 'rect', x: 0, y: H / 2, w: W, h: H / 2, fill: awayColor, alpha: 0.92 } : { t: 'rect', x: W / 2, y: 0, w: W / 2, h: H, fill: awayColor, alpha: 0.92 });
    }

    if (style.layout.frame) {
        const inset = m / 2;
        ops.push({ t: 'frame', x: inset, y: inset, w: W - 2 * inset, h: H - 2 * inset, color: style.layout.frame_color, width: style.layout.frame_width * u, radius: style.layout.radius * u });
    }

    // ── cabeçalho: competição e fase ─────────────────────────────────────────────
    let top = y0;
    const logoSize = 8 * u * Math.min(S, 1.2);
    const hasLogo = style.elements.org_logo && data.org.logo !== null;
    const headerMaxW = hasLogo ? CW - logoSize - 2 * u : CW;

    if (hasLogo && data.org.logo) {
        ops.push({ t: 'image', src: data.org.logo, x: x0 + CW - logoSize, y: y0, w: logoSize, h: logoSize });
    }

    if (style.elements.competition) {
        const title = `${data.competition}`;
        text(title, anchorX, top + 2 * u * S, 3.3 * u * S, p.text, 'heading', { maxW: headerMaxW, upper: true });
        top += 4.4 * u * S;
    }

    if (style.elements.phase) {
        const label = phaseLabel(data);
        text(label, anchorX, top + 1.2 * u * S, 2.3 * u * S, soft, 'body', { maxW: headerMaxW, alpha: softAlpha });
        top += 3.4 * u * S;
    }

    // ── rodapé ───────────────────────────────────────────────────────────────────
    const footerH = style.elements.footer && (style.text.footer !== '' || style.text.hashtag !== '') ? 5 * u * S : 0;
    const bottom = y0 + CH - footerH;

    if (footerH > 0) {
        const fy = y0 + CH - 1.8 * u * S;
        if (style.text.footer !== '' && style.text.hashtag !== '') {
            text(style.text.footer, x0, fy, 2.4 * u * S, soft, 'body', { align: 'left', maxW: CW * 0.62, alpha: softAlpha });
            text(style.text.hashtag, x0 + CW, fy, 2.6 * u * S, p.accent, 'heading', { align: 'right', maxW: CW * 0.34 });
        } else if (style.text.footer !== '') {
            text(style.text.footer, anchorX, fy, 2.4 * u * S, soft, 'body', { alpha: softAlpha });
        } else {
            text(style.text.hashtag, anchorX, fy, 2.8 * u * S, p.accent, 'heading');
        }
    }

    // ── título da arte ───────────────────────────────────────────────────────────
    const defaultHeadline = data.kind === 'preview' ? 'Jogo do dia' : data.kind === 'result' ? (data.decided ? 'Fim de jogo' : data.status_label) : 'Escalação';
    const headline = style.text.headline !== '' ? style.text.headline : defaultHeadline;
    const headlineSize = 7 * u * S * (variant === 'poster' ? 1.15 : 1);
    top += 1.5 * u;
    text(headline, anchorX, top + headlineSize * 0.55, headlineSize, p.accent, 'heading', { upper: true });
    top += headlineSize * 1.25;

    if (variant === 'poster') {
        ops.push({ t: 'rect', x: left ? x0 : x0 + CW / 2 - 6 * u, y: top, w: 12 * u, h: 0.9 * u, fill: p.primary });
        top += 2.5 * u;
    }

    if (data.kind === 'lineup') {
        lineupBlock(ops, style, data, { x0, CW, top, bottom, u, S, homeColor, awayColor, text });

        return ops;
    }

    // ── bloco das equipes + informações ─────────────────────────────────────────
    const infoH = data.kind === 'preview' ? 17 * u * S : 12 * u * S;
    const infoTop = bottom - infoH;
    const blockTop = top + 1.5 * u;
    const blockBottom = infoTop - 3 * u;
    const availH = Math.max(blockBottom - blockTop, 10 * u);
    const poster = variant === 'poster';
    const result = data.kind === 'result';
    const scoreSize = Math.min(12 * u * S * (poster ? 1.2 : 1), availH * 0.42, CW * 0.2);
    // Largura reservada ao meio (placar ou "×"): as colunas das equipes ficam dos lados, sem encostar nele.
    const centerW = stacked ? 0 : (result ? scoreSize * 3.1 : 8 * u * S) + 3 * u;
    const colW = stacked ? CW : (CW - centerW) / 2;
    const crestMax = style.crest.size * u * (poster ? 1.25 : 1);
    const cs = style.crest.show
        ? Math.max(Math.min(crestMax, stacked ? availH * 0.24 : availH * (poster ? 0.5 : 0.58), colW * 0.92), 6 * u)
        : 0;
    const nameMaxW = split && !stacked ? W * 0.46 : colW;
    // Um só tamanho para os dois nomes (o maior define): evita um nome miúdo ao lado de outro enorme.
    const longest = Math.max(data.home.name.length, data.away.name.length, 3);
    const nameSize = Math.max(
        Math.min((poster ? 5 : 3.6) * u * S, stacked ? availH * 0.05 : (availH - cs) * 0.3 || 3 * u, nameMaxW / (longest * 0.62)),
        1.6 * u,
    );
    const blockH = cs + (cs > 0 ? 3 * u : 0) + nameSize * 1.3;
    const crestAt = (centerY: number) => centerY - blockH / 2;
    const homeX = split && !stacked ? W * 0.25 : stacked ? W / 2 : x0 + colW / 2;
    const awayX = split && !stacked ? W * 0.75 : stacked ? W / 2 : x0 + CW - colW / 2;
    const centerY = blockTop + availH / 2;
    // Empilhado: equipe de cima colada no topo da área, a de baixo no fim, e o placar/× entre as duas.
    const homeY = stacked ? blockTop : crestAt(centerY);
    const awayY = stacked ? blockTop + availH - blockH : crestAt(centerY);
    const midY = stacked ? (homeY + blockH + awayY) / 2 : centerY;

    for (const [side, cx, cy] of [[data.home, homeX, homeY], [data.away, awayX, awayY]] as const) {
        if (cs > 0) crest(ops, style, side, cx, cy, cs, p);
        const label = side.name.length > 18 && side.short ? side.short : side.name;
        text(label, cx, cy + (cs > 0 ? cs + 3 * u : 0) + nameSize * 0.6, nameSize, p.text, poster ? 'heading' : 'body', { align: 'center', maxW: nameMaxW, upper: poster || style.type.uppercase, weight: 'bold' });
    }

    if (result) {
        const home = data.home.score ?? '–';
        const away = data.away.score ?? '–';
        if (split) ops.push({ t: 'rect', x: W / 2 - scoreSize * 1.7, y: midY - scoreSize * 0.75, w: scoreSize * 3.4, h: scoreSize * 1.5, fill: p.text, alpha: 0.14, radius: scoreSize * 0.4 });
        text(`${home} × ${away}`, W / 2, midY, scoreSize, p.text, 'heading', { align: 'center', maxW: stacked ? CW * 0.6 : centerW, upper: false });
    } else {
        text('×', W / 2, midY, Math.min(9 * u * S, availH * 0.3), p.accent, 'heading', { align: 'center', maxW: stacked ? CW * 0.2 : centerW });
    }

    // informações
    let y = infoTop + 2 * u * S;
    const centerInfo = variant === 'split' ? 'center' : align;
    const infoX = centerInfo === 'left' ? x0 : x0 + CW / 2;

    if (data.kind === 'preview') {
        const date = style.elements.date ? dateLabel(data.date) : null;
        const time = style.elements.time ? timeLabel(data.date) : null;
        if (date) {
            text(date, infoX, y, 4.4 * u * S, p.text, 'heading', { align: centerInfo, upper: true });
            y += 5.2 * u * S;
        }
        if (time) {
            text(time, infoX, y + 2 * u * S, 8 * u * S, variant === 'split' ? p.text : p.primary, 'heading', { align: centerInfo });
            y += 9 * u * S;
        }
        if (style.elements.venue && data.venue) text(data.venue, infoX, y, 2.8 * u * S, soft, 'body', { align: centerInfo, alpha: softAlpha });
        if (style.elements.status && data.status !== 'scheduled') text(data.status_label, infoX, infoTop - 0.5 * u, 2.8 * u * S, p.accent, 'heading', { align: centerInfo, upper: true });
    } else {
        if (style.elements.status) {
            text(data.status_label, infoX, y, 4 * u * S, p.accent, 'heading', { align: centerInfo, upper: true });
            y += 5 * u * S;
        }
        if (data.home.penalties !== null && data.away.penalties !== null) {
            text(`Pênaltis ${data.home.penalties} × ${data.away.penalties}`, infoX, y, 3 * u * S, p.text, 'body', { align: centerInfo });
            y += 3.6 * u * S;
        }
        const when = [style.elements.date ? dateLabel(data.date) : null, style.elements.time ? timeLabel(data.date) : null, style.elements.venue ? data.venue : null].filter(Boolean).join(' · ');
        text(when, infoX, y, 2.6 * u * S, soft, 'body', { align: centerInfo, alpha: softAlpha });
    }

    return ops;
}

function crest(ops: Op[], style: ArtStyle, side: ArtSide, cx: number, y: number, size: number, p: ArtStyle['palette']): void {
    const x = cx - size / 2;
    const shape = style.crest.shape;
    const fill = style.crest.use_team_colors && side.color ? side.color : style.crest.bg;

    if (shape !== 'none') ops.push({ t: 'shape', shape, x, y, w: size, h: size, fill, alpha: 1 });

    const pad = shape === 'none' ? 0 : size * 0.14;

    if (side.crest) {
        ops.push({ t: 'image', src: side.crest, x: x + pad, y: y + pad, w: size - 2 * pad, h: size - 2 * pad });
    } else {
        ops.push({ t: 'text', text: initials(side.short ?? side.name), x: cx, y: y + size / 2, size: size * 0.38, color: shape === 'none' ? p.text : p.primary, font: 'heading', align: 'center', maxW: size * 0.9, upper: true });
    }
}

interface LineupContext {
    x0: number;
    CW: number;
    top: number;
    bottom: number;
    u: number;
    S: number;
    homeColor: string;
    awayColor: string;
    text: (value: string, x: number, y: number, size: number, color: string, font: 'heading' | 'body', opts?: { align?: Align; maxW?: number; upper?: boolean; weight?: 'normal' | 'bold' | 'black'; alpha?: number }) => void;
}

function lineupBlock(ops: Op[], style: ArtStyle, data: ArtData, c: LineupContext): void {
    const { x0, CW, u, S, text } = c;
    const p = style.palette;

    if (data.lineup === null) {
        text('Escalação ainda não divulgada', x0 + CW / 2, (c.top + c.bottom) / 2, 4 * u * S, p.muted, 'body', { align: 'center' });

        return;
    }

    const gap = 3 * u;
    const colW = (CW - gap) / 2;
    const bands = style.layout.variant !== 'classic';
    const sides = [
        { side: data.home, lineup: data.lineup.home, x: x0, color: c.homeColor },
        { side: data.away, lineup: data.lineup.away, x: x0 + colW + gap, color: c.awayColor },
    ];
    const headH = 9 * u * S;
    const rows = Math.max(...sides.map((item) => item.lineup.starters.length), 1);
    const maxChars = Math.max(Math.floor(colW / (1.25 * u * S)), 12);
    const benchLines = Math.max(...sides.map((item) => (item.lineup.bench.length > 0 ? wrapWords(item.lineup.bench.map((player) => `${player.shirt ?? ''} ${player.name}`.trim()), maxChars).slice(0, 3).length : 0)));
    const benchH = (benchLines > 0 ? (benchLines + 1) * 2.7 * u * S : 0) + 3.4 * u * S;
    const rowH = Math.max(Math.min(5.4 * u * S, (c.bottom - c.top - headH - benchH) / rows), 2.6 * u);
    const font = rowH * 0.6;

    for (const { side, lineup, x, color } of sides) {
        if (bands) ops.push({ t: 'rect', x, y: c.top, w: colW, h: headH - 1.5 * u, fill: color, alpha: 0.9, radius: style.layout.radius * u });

        const cs = headH - 3 * u;
        const textX = x + (style.crest.show ? cs + 2 * u : 1.5 * u);
        if (style.crest.show) crest(ops, style, side, x + cs / 2 + 1 * u, c.top + 0.5 * u, cs, p);
        text(side.name, textX, c.top + (lineup.formation ? 2.6 : 3.7) * u * S, 3.4 * u * S, p.text, 'heading', { align: 'left', maxW: colW - (textX - x) - u, upper: true });
        if (lineup.formation) text(lineup.formation, textX, c.top + 5.6 * u * S, 2.3 * u * S, bands ? p.text : p.muted, 'body', { align: 'left', maxW: colW - (textX - x) - u });

        let y = c.top + headH + rowH / 2;

        for (const player of lineup.starters) {
            text(player.shirt !== null ? String(player.shirt) : '', x + 4 * u * S, y, font, p.accent, 'heading', { align: 'right', maxW: 4 * u * S });
            text(player.name + (player.captain ? ' (C)' : ''), x + 6 * u * S, y, font, p.text, 'body', { align: 'left', maxW: colW - 6 * u * S - (player.position ? 8 * u : 0) });
            if (player.position) text(player.position, x + colW, y, font * 0.8, p.muted, 'body', { align: 'right', maxW: 7 * u });
            y += rowH;
        }

        y = c.top + headH + rows * rowH + 1.5 * u;

        if (lineup.bench.length > 0) {
            text('Reservas', x, y + 1 * u, 2.2 * u * S, p.muted, 'body', { align: 'left', upper: true });
            y += 2.7 * u * S;
            for (const line of wrapWords(lineup.bench.map((player) => `${player.shirt ?? ''} ${player.name}`.trim()), maxChars).slice(0, 3)) {
                text(line, x, y + 1 * u, 2.3 * u * S, p.text, 'body', { align: 'left', maxW: colW });
                y += 2.7 * u * S;
            }
        }

        if (lineup.coach) text(`Técnico: ${lineup.coach}`, x, c.bottom - 1.2 * u, 2.4 * u * S, p.muted, 'body', { align: 'left', maxW: colW });
    }
}

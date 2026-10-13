// Motor de artes das partidas (ADR 0009): CÓDIGO PURO. Recebe estilo + dados + tamanho e devolve a lista de operações de
// desenho; quem desenha (canvas) está em artsDraw.ts. Testado em Node: `npm run test:js`.

export type FontKey = 'sans' | 'display' | 'condensed' | 'serif' | 'mono' | 'inter' | 'oswald' | 'bebas' | 'anton' | 'montserrat' | 'roboto-condensed' | 'playfair' | 'marker' | 'barlow' | 'archivo';

export interface ArtStyle {
    background: { mode: 'solid' | 'gradient'; color: string; color2: string; angle: number; pattern: 'none' | 'stripes' | 'grid' | 'dots'; pattern_opacity: number; shade: number; image: string | null; image_fit: 'cover' | 'contain'; image_opacity: number; tint: string; tint_opacity: number };
    logo: { asset: string | null; size: number; position: 'corner' | 'top' };
    palette: { primary: string; secondary: string; text: string; muted: string; accent: string };
    type: { heading: FontKey; body: FontKey; scale: number; weight: 'normal' | 'bold' | 'black'; uppercase: boolean; spacing: number };
    layout: { variant: 'classic' | 'split' | 'poster'; align: 'center' | 'left'; margin: number; frame: boolean; frame_width: number; frame_color: string; radius: number };
    crest: { show: boolean; size: number; shape: 'none' | 'circle' | 'rounded' | 'badge'; bg: string; use_team_colors: boolean };
    elements: { competition: boolean; phase: boolean; date: boolean; time: boolean; venue: boolean; status: boolean; org_logo: boolean; footer: boolean; broadcast: boolean };
    broadcast: { platform: 'none' | 'youtube' | 'twitch' | 'facebook' | 'instagram' | 'kick'; label: string; channel: string; badge: string | null };
    text: { title: string; subtitle: string; headline: string; credit: string; footer: string; hashtag: string };
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

export interface ArtGame {
    id: string;
    date: string | null;
    venue: string | null;
    status: string;
    status_label: string;
    decided: boolean;
    home: ArtSide;
    away: ArtSide;
}

export interface ArtData {
    kind: 'preview' | 'result' | 'lineup' | 'round' | 'cover' | 'standings';
    competition: string;
    season: string;
    division: string;
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
    scope: 'day' | 'round';
    games: ArtGame[];
    standings: ArtStandings | null;
}

export interface ArtStandingRow {
    entry: string;
    position: number;
    name: string;
    short: string | null;
    crest: string | null;
    played: number;
    wins: number;
    draws: number;
    losses: number;
    diff: number;
    points: number;
}

export interface ArtStandings {
    title: string;
    unit: string;
    rows: ArtStandingRow[];
    highlight: string[];
}

export type Align = 'left' | 'center' | 'right';

export type Op =
    | { t: 'background' }
    | { t: 'bgimage'; src: string; fit: 'cover' | 'contain'; opacity: number }
    | { t: 'texture' }
    | { t: 'rect'; x: number; y: number; w: number; h: number; fill: string; alpha?: number; radius?: number }
    | { t: 'frame'; x: number; y: number; w: number; h: number; color: string; width: number; radius: number }
    | { t: 'text'; text: string; x: number; y: number; size: number; color: string; font: 'heading' | 'body'; align: Align; maxW: number; upper?: boolean; weight?: 'normal' | 'bold' | 'black'; alpha?: number }
    | { t: 'image'; src: string; x: number; y: number; w: number; h: number }
    | { t: 'shape'; shape: 'circle' | 'rounded' | 'badge'; x: number; y: number; w: number; h: number; fill: string; alpha?: number }
    | { t: 'poly'; points: number[]; fill: string };

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

/** "2026-10-14T16:30" → "QUARTA — 14/10" (cabeçalho de um dia na arte de rodada). */
export function dayHeading(date: string | null): string | null {
    const match = date?.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) return null;

    const [, year, month, day] = match.map(Number);
    const weekday = WEEKDAYS[new Date(Date.UTC(year, month - 1, day)).getUTCDay()];

    return `${weekday} — ${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}`;
}

const PLATFORM_BADGES: Record<string, { label: string; fill: string; ink: string }> = {
    youtube: { label: 'YouTube', fill: '#ff0000', ink: '#ffffff' },
    twitch: { label: 'Twitch', fill: '#9146ff', ink: '#ffffff' },
    facebook: { label: 'Facebook', fill: '#1877f2', ink: '#ffffff' },
    instagram: { label: 'Instagram', fill: '#e1306c', ink: '#ffffff' },
    kick: { label: 'Kick', fill: '#53fc18', ink: '#0b0e0f' },
};

function initials(name: string): string {
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

/** Monta a arte. Tudo em pixels do tamanho pedido; `u` = 1% do lado menor. */
/**
 * `assets` = endereço (mesma origem) das imagens da organização por id (fundo e logotipo escolhidos no estilo).
 * Imagem escolhida que não está no mapa foi excluída: a arte sai sem ela.
 */
export function buildArt(style: ArtStyle, data: ArtData, W: number, H: number, assets: Record<string, string> = {}): Op[] {
    const ops: Op[] = [{ t: 'background' }];
    const bgSrc = style.background.image ? assets[style.background.image] : undefined;

    if (bgSrc) ops.push({ t: 'bgimage', src: bgSrc, fit: style.background.image_fit, opacity: style.background.image_opacity });
    if (style.background.tint_opacity > 0) ops.push({ t: 'rect', x: 0, y: 0, w: W, h: H, fill: style.background.tint, alpha: style.background.tint_opacity });
    ops.push({ t: 'texture' });

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
    const teamsKind = data.kind === 'preview' || data.kind === 'result';
    const stacked = teamsKind && H / W > 1.5;
    const split = variant === 'split' && teamsKind;
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

    // ── rodapé e faixa de transmissão (desenhados antes: definem o limite de baixo) ──────
    const footerH = style.elements.footer && (style.text.footer !== '' || style.text.hashtag !== '') ? 5 * u * S : 0;
    const showBroadcast = style.elements.broadcast;
    const hasBadge = showBroadcast && style.broadcast.platform !== 'none';
    const broadcastH = showBroadcast ? ((hasBadge ? 5.5 : 0) + (style.broadcast.label !== '' ? 5.8 : 0) + (style.broadcast.channel !== '' ? 3.6 : 0)) * u * S : 0;
    const bottom = y0 + CH - footerH - broadcastH;

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

    if (showBroadcast && broadcastH > 0) {
        let by = bottom + 0.6 * u;
        if (hasBadge) {
            const badge = PLATFORM_BADGES[style.broadcast.platform];
            const bh = 4 * u * S;
            const bw = (style.broadcast.platform === 'youtube' ? 13 : 10.5) * u * S;
            const bx = left ? x0 : x0 + CW / 2 - bw / 2;
            ops.push({ t: 'shape', shape: 'rounded', x: bx, y: by, w: bw, h: bh, fill: badge.fill });
            if (style.broadcast.platform === 'youtube') {
                const tx = bx + bh * 0.35;
                ops.push({ t: 'poly', points: [tx, by + bh * 0.28, tx, by + bh * 0.72, tx + bh * 0.42, by + bh * 0.5], fill: badge.ink });
                ops.push({ t: 'text', text: badge.label, x: bx + bh * 1.05, y: by + bh / 2, size: bh * 0.52, color: badge.ink, font: 'body', align: 'left', maxW: bw - bh * 1.2, upper: false, weight: 'black' });
            } else {
                ops.push({ t: 'text', text: badge.label, x: bx + bw / 2, y: by + bh / 2, size: bh * 0.5, color: badge.ink, font: 'body', align: 'center', maxW: bw * 0.9, upper: false, weight: 'black' });
            }
            by += 5.5 * u * S;
        }
        if (style.broadcast.label !== '') {
            text(style.broadcast.label, anchorX, by + 2.2 * u * S, 4.6 * u * S, p.accent, 'heading', { upper: true });
            by += 5.8 * u * S;
        }
        if (style.broadcast.channel !== '') text(style.broadcast.channel, anchorX, by + 1.2 * u * S, 2.8 * u * S, soft, 'body', { upper: true, alpha: softAlpha });
    }

    const logoSrc = style.logo.asset ? (assets[style.logo.asset] ?? null) : data.org.logo;
    const hasLogo = style.elements.org_logo && logoSrc !== null;

    if (data.kind === 'cover') {
        coverBlock(ops, style, data, { x0, y0, CW, CH, bottom, u, S, anchorX, align, text, soft, softAlpha, hasLogo, logoSrc });

        return ops;
    }

    // ── cabeçalho: competição, fase e subtítulo ─────────────────────────────────────
    let top = y0;
    const logoSize = style.logo.size * u * Math.min(S, 1.2);
    const headerMaxW = hasLogo ? CW - logoSize - 2 * u : CW;

    if (hasLogo && logoSrc) {
        ops.push({ t: 'image', src: logoSrc, x: x0 + CW - logoSize, y: y0, w: logoSize, h: logoSize });
    }

    if (style.elements.competition) {
        const title = style.text.title !== '' ? style.text.title : data.competition;
        text(title, anchorX, top + 2 * u * S, 3.3 * u * S, p.text, 'heading', { maxW: headerMaxW, upper: true });
        top += 4.4 * u * S;
    }

    if (style.elements.phase) {
        const label = phaseLabel(data);
        text(label, anchorX, top + 1.2 * u * S, 2.3 * u * S, soft, 'body', { maxW: headerMaxW, alpha: softAlpha });
        top += 3.4 * u * S;
    }

    if (style.text.subtitle !== '') {
        text(style.text.subtitle, anchorX, top + 1.6 * u * S, 3 * u * S, p.text, 'heading', { maxW: headerMaxW, upper: true });
        top += 4 * u * S;
    }

    // ── título da arte ───────────────────────────────────────────────────────────
    const roundNumber = data.scope === 'round' && data.round !== null ? `Rodada ${data.round}` : 'Jogos do dia';
    const defaultHeadline =
        data.kind === 'preview' ? 'Jogo do dia' : data.kind === 'result' ? (data.decided ? 'Fim de jogo' : data.status_label) : data.kind === 'round' ? roundNumber : data.kind === 'standings' ? (data.standings?.title ?? 'Classificação') : 'Escalação';
    const headline = style.text.headline !== '' ? style.text.headline : defaultHeadline;
    const headlineSize = 7 * u * S * (variant === 'poster' ? 1.15 : 1);
    top += 1.5 * u;
    text(headline, anchorX, top + headlineSize * 0.55, headlineSize, p.accent, 'heading', { upper: true });
    top += headlineSize * 1.25;

    if (variant === 'poster') {
        ops.push({ t: 'rect', x: left ? x0 : x0 + CW / 2 - 6 * u, y: top, w: 12 * u, h: 0.9 * u, fill: p.primary });
        top += 2.5 * u;
    }

    if (data.kind === 'round') {
        roundBlock(ops, style, data, { x0, CW, top, bottom, u, S, text, soft, softAlpha });

        return ops;
    }

    if (data.kind === 'standings') {
        standingsBlock(ops, style, data, { x0, CW, top, bottom, u, S, text, soft, softAlpha });

        return ops;
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

type TextFn = (value: string, x: number, y: number, size: number, color: string, font: 'heading' | 'body', opts?: { align?: Align; maxW?: number; upper?: boolean; weight?: 'normal' | 'bold' | 'black'; alpha?: number }) => void;

interface RoundContext {
    x0: number;
    CW: number;
    top: number;
    bottom: number;
    u: number;
    S: number;
    text: TextFn;
    soft: string;
    softAlpha: number | undefined;
}

/** Arte de rodada/dia: vários jogos, agrupados por dia, em lista, painéis coloridos ou cartazes (conforme o estilo do layout). */
function roundBlock(ops: Op[], style: ArtStyle, data: ArtData, c: RoundContext): void {
    const { x0, CW, u, S, text } = c;
    const p = style.palette;
    const variant = style.layout.variant;
    const games = data.games;

    if (games.length === 0) {
        text('Nenhum jogo encontrado', x0 + CW / 2, (c.top + c.bottom) / 2, 4 * u * S, p.muted, 'body', { align: 'center' });

        return;
    }

    const groups: { day: string | null; games: ArtGame[] }[] = [];

    for (const game of games) {
        const day = game.date ? game.date.slice(0, 10) : null;
        const last = groups[groups.length - 1];

        if (last && last.day === day) last.games.push(game);
        else groups.push({ day, games: [game] });
    }

    const venues = [...new Set(games.map((game) => game.venue).filter((venue): venue is string => Boolean(venue)))];
    const singleVenue = style.elements.venue && venues.length === 1 ? venues[0] : null;
    const venueH = singleVenue ? 6.5 * u * S : 0;
    const dayHeadings = style.elements.date ? groups.filter((group) => group.day !== null).length : 0;
    const dayH = 5.6 * u * S;
    const gap = 1.8 * u;
    const rowMax = variant === 'poster' ? 52 * u : variant === 'split' ? 26 * u : 16 * u;
    const avail = c.bottom - c.top - venueH - dayHeadings * dayH - (games.length - 1) * gap;
    const rowH = Math.max(Math.min(rowMax, avail / games.length), 6 * u);
    const used = dayHeadings * dayH + games.length * rowH + (games.length - 1) * gap + venueH;
    // O bloco de jogos fica um pouco acima do centro do espaço livre (não colado no título nem no rodapé).
    const offset = Math.max((c.bottom - c.top - used) * 0.4, 0);
    const timeW = variant === 'poster' ? 0 : 15 * u * S;
    const dark = style.background.color;
    let y = c.top + offset;

    for (const group of groups) {
        if (style.elements.date && group.day !== null) {
            text(dayHeading(group.day) ?? '', variant === 'poster' ? x0 + CW / 2 : x0 + CW, y + dayH * 0.45, 4.2 * u * S, p.text, 'heading', { align: variant === 'poster' ? 'center' : 'right', upper: true });
            y += dayH;
        }

        for (const game of group.games) {
            const cy = y + rowH / 2;
            const time = style.elements.time ? timeLabel(game.date) : null;
            const live = game.status === 'in_progress';
            const middle = game.decided || live ? `${game.home.score ?? 0} × ${game.away.score ?? 0}` : '×';
            const gridX = x0 + timeW + (variant === 'split' ? 1.5 * u : 0);
            const gridW = x0 + CW - gridX;
            const mid = gridX + gridW / 2;

            if (variant === 'classic') {
                const cs = Math.max(Math.min(rowH * 0.78, 14 * u), 4 * u);
                const midSize = Math.min(rowH * (game.decided || live ? 0.46 : 0.5), 6.5 * u * S);
                const g = Math.max(7 * u * S, middle.length > 1 ? midSize * 0.62 * middle.length : 0);
                const hx = mid - g / 2 - cs / 2;
                const ax = mid + g / 2 + cs / 2;
                const nameSize = Math.min(rowH * 0.3, 3.4 * u * S);
                if (style.crest.show) {
                    crest(ops, style, game.home, hx, cy - cs / 2, cs, p);
                    crest(ops, style, game.away, ax, cy - cs / 2, cs, p);
                }
                text(middle, mid, cy, midSize, p.accent, 'heading', { align: 'center', maxW: g, upper: false });
                text(game.home.name, hx - cs / 2 - 1.8 * u, cy, nameSize, p.text, 'body', { align: 'right', maxW: Math.max(hx - cs / 2 - 1.8 * u - gridX, 4 * u), upper: style.type.uppercase, weight: 'bold' });
                text(game.away.name, ax + cs / 2 + 1.8 * u, cy, nameSize, p.text, 'body', { align: 'left', maxW: Math.max(x0 + CW - (ax + cs / 2 + 1.8 * u), 4 * u), upper: style.type.uppercase, weight: 'bold' });
                if (time) text(time, x0, cy - (live || (style.elements.venue && !singleVenue) ? 1.6 * u : 0), Math.min(rowH * 0.42, 5.4 * u * S), p.accent, 'heading', { align: 'left', maxW: timeW - u, upper: false });
            } else if (variant === 'split') {
                const chipH = Math.min(rowH * 0.6, 14 * u);
                if (time) {
                    ops.push({ t: 'rect', x: x0, y: cy - chipH / 2, w: timeW - 1.5 * u, h: chipH, fill: p.accent, radius: style.layout.radius * u });
                    text(time, x0 + (timeW - 1.5 * u) / 2, cy, Math.min(chipH * 0.5, 5 * u * S), dark, 'heading', { align: 'center', maxW: timeW - 3 * u, upper: false });
                }
                ops.push({ t: 'rect', x: gridX, y, w: gridW, h: rowH, fill: p.accent, alpha: 0.96, radius: style.layout.radius * u });
                const cs = Math.max(Math.min(rowH * 0.84, gridW * 0.38), 4 * u);
                const slots: [ArtSide, number][] = [[game.home, gridX + gridW * 0.27], [game.away, gridX + gridW * 0.73]];
                for (const [side, cx] of slots) {
                    if (style.crest.show && side.crest) crest(ops, style, side, cx, cy - cs / 2, cs, p);
                    else text(side.name, cx, cy, Math.min(rowH * 0.2, 3.4 * u * S), dark, 'heading', { align: 'center', maxW: gridW * 0.4, upper: true });
                }
                if (game.decided || live) text(middle, gridX + gridW / 2, cy, Math.min(rowH * 0.34, 6 * u * S), dark, 'heading', { align: 'center', maxW: gridW * 0.2, upper: false });
            } else {
                const cs = Math.max(Math.min(rowH * 0.5, CW * 0.3), 4 * u);
                const nameSize = Math.min(rowH * 0.13, 4.6 * u * S);
                const hx = x0 + CW * 0.27;
                const ax = x0 + CW * 0.73;
                const blockH = cs + 2.4 * u + nameSize * 1.2;
                const crestY = cy - blockH / 2 + (time ? 2 * u : 0);
                if (time) text(time, x0 + CW / 2, crestY - 2.6 * u, Math.min(rowH * 0.1, 4.4 * u * S), p.accent, 'heading', { align: 'center', upper: false });
                if (style.crest.show) {
                    crest(ops, style, game.home, hx, crestY, cs, p);
                    crest(ops, style, game.away, ax, crestY, cs, p);
                }
                text(middle, x0 + CW / 2, crestY + cs / 2, Math.min(cs * 0.34, 7 * u * S), p.accent, 'heading', { align: 'center', maxW: CW * 0.2, upper: false });
                text(game.home.name, hx, crestY + cs + 2.4 * u + nameSize * 0.55, nameSize, p.text, 'heading', { align: 'center', maxW: CW * 0.42, upper: true });
                text(game.away.name, ax, crestY + cs + 2.4 * u + nameSize * 0.55, nameSize, p.text, 'heading', { align: 'center', maxW: CW * 0.42, upper: true });
            }

            if (live) {
                const w = 10 * u * S;
                ops.push({ t: 'shape', shape: 'rounded', x: x0, y: cy + (time ? 2 * u : -1.6 * u), w, h: 3 * u * S, fill: '#dc2626' });
                ops.push({ t: 'text', text: 'Ao vivo', x: x0 + w / 2, y: cy + (time ? 2 * u : -1.6 * u) + 1.5 * u * S, size: 1.9 * u * S, color: '#ffffff', font: 'heading', align: 'center', maxW: w * 0.9, upper: true });
            }

            if (style.elements.venue && !singleVenue && game.venue && variant === 'classic') {
                text(game.venue, x0, cy + 3.4 * u * S, 1.9 * u * S, c.soft, 'body', { align: 'left', maxW: timeW - u, alpha: c.softAlpha });
            }

            y += rowH + gap;
        }
    }

    if (singleVenue) {
        const vy = y - gap + venueH / 2 + 0.8 * u;
        if (variant === 'split') {
            ops.push({ t: 'rect', x: x0 + timeW + 1.5 * u, y: vy - 2.6 * u * S, w: CW - timeW - 1.5 * u, h: 5.2 * u * S, fill: p.accent, radius: style.layout.radius * u });
            text(singleVenue, x0 + timeW + 1.5 * u + (CW - timeW - 1.5 * u) / 2, vy, 3 * u * S, dark, 'body', { align: 'center', maxW: CW - timeW - 4 * u, upper: true, weight: 'bold' });
        } else {
            text(singleVenue, x0 + CW / 2, vy, 3 * u * S, p.text, 'body', { align: 'center', upper: true, weight: 'bold' });
        }
    }
}

interface CoverContext {
    x0: number;
    y0: number;
    CW: number;
    CH: number;
    bottom: number;
    u: number;
    S: number;
    anchorX: number;
    align: Align;
    text: TextFn;
    soft: string;
    softAlpha: number | undefined;
    hasLogo: boolean;
    logoSrc: string | null;
}

/** Capa/anúncio: título grande da competição, subtítulo, chamada e crédito (ex.: "Transmissão ao vivo", "Narração: ..."). */
function coverBlock(ops: Op[], style: ArtStyle, data: ArtData, c: CoverContext): void {
    const { x0, y0, CW, CH, u, S, anchorX, text } = c;
    const p = style.palette;
    const left = style.layout.align === 'left';
    const title = style.text.title !== '' ? style.text.title : data.competition;
    const logo = c.hasLogo ? c.logoSrc : null;
    const logoSize = 15 * u * Math.min(S, 1.3);
    const small = style.elements.phase ? data.division || data.season : '';
    const titleSize = 17 * u * S;
    const blockH = (logo ? logoSize + 3 * u : 0) + (small ? 5 * u * S : 0) + titleSize * 1.1 + (style.text.subtitle ? 8 * u * S : 0);
    const avail = c.bottom - y0;
    let y = y0 + Math.max((avail - blockH) * 0.42, 0);

    if (logo) {
        ops.push({ t: 'image', src: logo, x: left ? x0 : anchorX - logoSize / 2, y, w: logoSize, h: logoSize });
        y += logoSize + 3 * u;
    }

    if (small) {
        text(small, anchorX, y + 1.8 * u * S, 3.6 * u * S, p.accent, 'heading', { upper: true });
        y += 5 * u * S;
    }

    text(title, anchorX, y + titleSize * 0.5, titleSize, p.accent, 'heading', { upper: true });
    y += titleSize * 1.1;

    if (style.text.subtitle !== '') {
        text(style.text.subtitle, anchorX, y + 3 * u * S, 6 * u * S, p.text, 'heading', { upper: true });
    }

    const lowerTop = y0 + CH * 0.66;
    let ly = Math.max(lowerTop, y + 14 * u * S);
    const headline = style.text.headline;

    if (headline !== '' && ly < c.bottom - 6 * u) {
        text(headline, anchorX, ly, 6.4 * u * S, p.text, 'heading', { upper: true });
        ly += 8 * u * S;
    }

    if (style.text.credit !== '' && ly < c.bottom - 3 * u) {
        text(style.text.credit, anchorX, ly, 4 * u * S, p.text, 'heading', { upper: true });
    }
}

interface StandingsContext {
    x0: number;
    CW: number;
    top: number;
    bottom: number;
    u: number;
    S: number;
    text: TextFn;
    soft: string;
    softAlpha: number | undefined;
}

/** Tabela de classificação: posição, escudo, equipe, pontos, jogos, vitórias e saldo; as equipes do jogo ficam destacadas. */
function standingsBlock(ops: Op[], style: ArtStyle, data: ArtData, c: StandingsContext): void {
    const { x0, CW, u, S, text, soft, softAlpha } = c;
    const p = style.palette;
    const table = data.standings;

    if (table === null || table.rows.length === 0) {
        text('Classificação indisponível para esta fase', x0 + CW / 2, c.top + 8 * u, 3 * u * S, soft, 'body', { alpha: softAlpha, maxW: CW });

        return;
    }

    const rows = table.rows.slice(0, 16);
    const headH = 4 * u * S;
    const rowH = Math.min(7.5 * u * S, (c.bottom - c.top - headH) / rows.length);
    const size = Math.min(rowH * 0.5, 3.6 * u * S);
    const crestSize = style.crest.show ? rowH * 0.74 : 0;
    const cols = [
        { key: 'points', label: 'P', w: 5 * u * S },
        { key: 'played', label: 'J', w: 4.2 * u * S },
        { key: 'wins', label: 'V', w: 4.2 * u * S },
        { key: 'diff', label: 'SG', w: 5 * u * S },
    ] as const;
    const numbersW = cols.reduce((sum, col) => sum + col.w, 0);
    const posW = 5 * u * S;
    let y = c.top;

    let cx = x0 + CW - numbersW;
    for (const col of cols) {
        text(col.label, cx + col.w / 2, y + headH / 2, size * 0.8, soft, 'body', { alpha: softAlpha, weight: 'bold' });
        cx += col.w;
    }
    y += headH;

    rows.forEach((row, index) => {
        const mine = table.highlight.includes(row.entry);
        const mid = y + rowH / 2;

        if (mine) ops.push({ t: 'rect', x: x0, y: y + rowH * 0.06, w: CW, h: rowH * 0.88, fill: p.accent, alpha: 0.2, radius: rowH * 0.2 });
        else if (index % 2 === 0) ops.push({ t: 'rect', x: x0, y: y + rowH * 0.06, w: CW, h: rowH * 0.88, fill: p.text, alpha: 0.06, radius: rowH * 0.2 });

        text(String(row.position), x0 + posW / 2, mid, size, mine ? p.accent : p.text, 'heading', { weight: 'black' });
        let nx = x0 + posW;

        if (crestSize > 0) {
            if (row.crest) ops.push({ t: 'image', src: row.crest, x: nx, y: mid - crestSize / 2, w: crestSize, h: crestSize });
            else ops.push({ t: 'shape', shape: 'circle', x: nx, y: mid - crestSize / 2, w: crestSize, h: crestSize, fill: p.primary, alpha: 0.6 });
            nx += crestSize + 1.6 * u;
        }

        text(row.name, nx, mid, size, p.text, 'heading', { align: 'left', maxW: x0 + CW - numbersW - nx - u, upper: true, weight: mine ? 'black' : 'bold' });

        let vx = x0 + CW - numbersW;
        for (const col of cols) {
            const raw = row[col.key];
            const value = col.key === 'diff' && raw > 0 ? `+${raw}` : String(raw);
            text(value, vx + col.w / 2, mid, size, col.key === 'points' ? p.accent : p.text, col.key === 'points' ? 'heading' : 'body', { weight: col.key === 'points' ? 'black' : 'normal' });
            vx += col.w;
        }

        y += rowH;
    });
}

/**
 * Overlays de campeonato do OBS: configuração por URL, seleção dos itens de cada visão e modelos de estilo.
 * Código puro (testado em Node): a página só desenha o que sai daqui. Parâmetros compatíveis com o painel de origem.
 */

export type ObsView = 'standings' | 'fixtures' | 'results' | 'scorers' | 'discipline' | 'matchcard' | 'ticker' | 'champion';

export const OBS_VIEWS: { key: ObsView; label: string; hint: string; rows: number }[] = [
    { key: 'standings', label: 'Tabela de classificação', hint: 'Posição, pontos, jogos e saldo', rows: 8 },
    { key: 'fixtures', label: 'Próximos jogos', hint: 'Agenda com horário e local', rows: 5 },
    { key: 'results', label: 'Últimos resultados', hint: 'Placares finalizados e ao vivo', rows: 5 },
    { key: 'scorers', label: 'Artilharia', hint: 'Ranking de goleadores', rows: 5 },
    { key: 'discipline', label: 'Cartões e suspensos', hint: 'Disciplina do campeonato (se a organização liberar)', rows: 6 },
    { key: 'matchcard', label: 'Jogo em destaque', hint: 'Confronto, placar e horário', rows: 1 },
    { key: 'ticker', label: 'Faixa de placares', hint: 'Faixa rolante de resultados e jogos', rows: 12 },
    { key: 'champion', label: 'Campeão', hint: 'Apresentação do título', rows: 1 },
];

export const OBS_STYLES: [string, string][] = [['broadcast', 'Painel TV'], ['glass', 'Vidro'], ['neon', 'Neon'], ['light', 'Claro'], ['minimal', 'Minimal'], ['gradient', 'Gradiente'], ['stripe', 'Esportivo']];
export const OBS_ANIMATIONS: [string, string][] = [['rise', 'Subida'], ['slide', 'Deslize'], ['zoom', 'Zoom'], ['wipe', 'Cortina'], ['fade', 'Fade'], ['flip', 'Virada 3D'], ['cascade', 'Cascata de linhas'], ['bounce', 'Salto elástico'], ['drop', 'Queda do topo']];
export const OBS_POSITIONS: [string, string][] = [['tl', 'Topo esq.'], ['tc', 'Topo centro'], ['tr', 'Topo dir.'], ['cl', 'Meio esq.'], ['cc', 'Centro'], ['cr', 'Meio dir.'], ['bl', 'Base esq.'], ['bc', 'Base centro'], ['br', 'Base dir.']];
export const OBS_COLUMNS = ['min', 'std', 'full'] as const;

const VIEW_DEFAULTS: Record<ObsView, { pos: string; width: number }> = {
    standings: { pos: 'cl', width: 760 },
    fixtures: { pos: 'bl', width: 860 },
    results: { pos: 'bl', width: 860 },
    scorers: { pos: 'cr', width: 640 },
    discipline: { pos: 'cr', width: 720 },
    matchcard: { pos: 'bc', width: 1060 },
    ticker: { pos: 'bc', width: 1920 },
    champion: { pos: 'cc', width: 760 },
};

export const OBS_DEFAULTS = { style: 'broadcast', anim: 'rise', dx: 0, dy: 0, scale: 100, surface: 94, radius: 10, logos: 1, hold: 0, gap: 20, rotate: 8, refresh: 15, cols: 'std', speed: 90, bw: 0, sh: 0, pad: 100, blur: 0, head: 1, dots: 1 } as const;

export interface ObsConfig {
    view: ObsView;
    edicao: string;
    div: string;
    c: string;
    match: string;
    style: string;
    anim: string;
    pos: string;
    dx: number;
    dy: number;
    scale: number;
    width: number;
    rows: number;
    font: string;
    accent: string;
    bg: string;
    text: string;
    surface: number;
    radius: number;
    logos: 0 | 1;
    title: string;
    subtitle: string;
    group: string;
    round: string;
    hold: number;
    gap: number;
    rotate: number;
    refresh: number;
    cols: 'min' | 'std' | 'full';
    speed: number;
    bw: number;
    bc: string;
    sh: number;
    pad: number;
    blur: number;
    head: 0 | 1;
    dots: 0 | 1;
    /** 1 = a tela só aparece quando o operador a coloca no ar (comando ao vivo do painel/API). */
    live: 0 | 1;
}

export const OBS_STYLE_KEYS = ['style', 'anim', 'accent', 'bg', 'text', 'font', 'surface', 'radius', 'scale', 'bw', 'bc', 'sh', 'pad', 'blur', 'head', 'dots', 'logos', 'pos', 'hold', 'gap', 'rotate', 'speed', 'refresh'] as const;

const clamp = (raw: string | null, min: number, max: number, fallback: number): number => {
    if (raw === null || raw === '') return fallback;
    const value = Number(raw);

    return Number.isFinite(value) ? Math.min(max, Math.max(min, value)) : fallback;
};

export function isObsView(value: string): value is ObsView {
    return OBS_VIEWS.some((view) => view.key === value);
}

/** Lê e valida a configuração da URL: qualquer valor fora da faixa ou da lista volta ao padrão. */
export function obsConfig(search: string, viewName: string, fonts: string[] = []): ObsConfig {
    const params = new URLSearchParams(search);
    const view: ObsView = isObsView(viewName) ? viewName : 'standings';
    const info = OBS_VIEWS.find((item) => item.key === view)!;
    const pick = (key: string, list: [string, string][], fallback: string) => (list.some(([value]) => value === params.get(key)) ? (params.get(key) as string) : fallback);
    const number = (key: string, min: number, max: number, fallback: number) => clamp(params.get(key), min, max, fallback);
    const color = (key: string) => (/^#?[0-9a-f]{6}$/i.test(params.get(key) ?? '') ? `#${(params.get(key) as string).replace('#', '')}` : '');
    const text = (key: string, max: number) => (params.get(key) ?? '').slice(0, max);
    const defaults = VIEW_DEFAULTS[view];
    const cols = (OBS_COLUMNS as readonly string[]).includes(params.get('cols') ?? '') ? (params.get('cols') as ObsConfig['cols']) : 'std';
    const width = defaults.width + (view === 'standings' && cols === 'full' ? 160 : 0);
    const font = params.get('font') ?? '';

    return {
        view,
        edicao: text('edicao', 40),
        div: text('div', 40),
        c: text('c', 80),
        match: text('match', 40),
        style: pick('style', OBS_STYLES, OBS_DEFAULTS.style),
        anim: pick('anim', OBS_ANIMATIONS, OBS_DEFAULTS.anim),
        pos: pick('pos', OBS_POSITIONS, defaults.pos),
        dx: number('dx', -1000, 1000, 0),
        dy: number('dy', -600, 600, 0),
        scale: number('scale', 50, 200, 100),
        width: number('width', 360, 1920, width),
        rows: Math.round(number('rows', 1, 30, info.rows)),
        font: fonts.includes(font) ? font : 'global',
        accent: color('accent'),
        bg: color('bg'),
        text: color('text'),
        surface: number('surface', 30, 100, OBS_DEFAULTS.surface),
        radius: number('radius', 0, 28, OBS_DEFAULTS.radius),
        logos: params.get('logos') === '0' ? 0 : 1,
        title: text('title', 60),
        subtitle: text('subtitle', 60),
        group: text('group', 40),
        round: text('round', 60),
        hold: number('hold', 0, 600, 0),
        gap: number('gap', 3, 900, OBS_DEFAULTS.gap),
        rotate: number('rotate', 3, 120, OBS_DEFAULTS.rotate),
        refresh: number('refresh', 5, 300, OBS_DEFAULTS.refresh),
        cols,
        speed: number('speed', 20, 400, OBS_DEFAULTS.speed),
        bw: number('bw', 0, 12, 0),
        bc: color('bc'),
        sh: number('sh', 0, 80, 0),
        pad: number('pad', 50, 220, 100),
        blur: number('blur', 0, 30, 0),
        head: params.get('head') === '0' ? 0 : 1,
        dots: params.get('dots') === '0' ? 0 : 1,
        live: params.get('live') === '1' ? 1 : 0,
    };
}

/** Só grava na URL o que difere do padrão da visão (URL curta e estável). */
export function obsQuery(cfg: ObsConfig): string {
    const info = OBS_VIEWS.find((item) => item.key === cfg.view)!;
    const defaults = VIEW_DEFAULTS[cfg.view];
    const params = new URLSearchParams();
    const plain: Record<string, string | number> = {
        style: OBS_DEFAULTS.style, anim: OBS_DEFAULTS.anim, dx: 0, dy: 0, scale: 100, surface: OBS_DEFAULTS.surface, radius: OBS_DEFAULTS.radius, logos: 1,
        hold: 0, gap: OBS_DEFAULTS.gap, rotate: OBS_DEFAULTS.rotate, refresh: OBS_DEFAULTS.refresh, cols: 'std', speed: OBS_DEFAULTS.speed, font: 'global',
        accent: '', bg: '', text: '', bw: 0, bc: '', sh: 0, pad: 100, blur: 0, head: 1, dots: 1, title: '', subtitle: '', group: '', round: '', live: 0, pos: defaults.pos,
        width: defaults.width + (cfg.view === 'standings' && cfg.cols === 'full' ? 160 : 0), rows: info.rows,
    };

    for (const key of ['edicao', 'div', 'c'] as const) if (cfg[key]) params.set(key, cfg[key]);
    if (cfg.match && cfg.view === 'matchcard') params.set('match', cfg.match);

    for (const [key, fallback] of Object.entries(plain)) {
        const value = (cfg as unknown as Record<string, string | number>)[key];
        if (value !== undefined && value !== fallback && value !== '') params.set(key, String(value).replace(/^#/, ''));
    }

    return params.toString();
}

export function obsUrl(base: string, cfg: ObsConfig): string {
    const query = obsQuery(cfg);

    return `${base}/${cfg.view}${query ? `?${query}` : ''}`;
}

// ───────────── Dados ─────────────

export interface ObsTeam { id: string; name: string; short: string | null; logo: string | null; color: string | null }
export interface ObsStandingRow { position: number; team_id: string; name: string; played: number; won: number; drawn: number; lost: number; gd: number; points: number }
export interface ObsMatch {
    id: string; round: string; status: 'scheduled' | 'live' | 'finished' | 'cancelled'; kickoff_at: string | null;
    home_team_id: string | null; away_team_id: string | null; home_name: string; away_name: string; home_score: number | null; away_score: number | null; venue: string | null;
}
export interface ObsScorer { name: string; team_name: string | null; team_id: string | null; goals: number }
export interface ObsCard { name: string; team_name: string | null; team_id: string | null; yellow: number; red: number }
export interface ObsBundle {
    championship: { name: string; season: string; division: string; logo: string | null; champion_id: string | null };
    divisions: { id: string; name: string }[];
    teams: ObsTeam[];
    standings: ObsStandingRow[];
    groups: { group: string; table: ObsStandingRow[] }[];
    matches: ObsMatch[];
    scorers: ObsScorer[];
    cards: ObsCard[] | null;
}

export type ObsItem = ObsStandingRow | ObsMatch | ObsScorer | ObsCard;

const time = (match: ObsMatch): number => (match.kickoff_at ? new Date(match.kickoff_at).getTime() : Number.MAX_SAFE_INTEGER);

/** Itens de cada visão (a página do portal e esta usam os mesmos jogos publicados). */
export function obsItems(bundle: ObsBundle, cfg: ObsConfig): ObsItem[] {
    const matches = bundle.matches.filter((match) => match.status !== 'cancelled' && (!cfg.round || match.round === cfg.round));

    switch (cfg.view) {
        case 'standings': {
            const group = cfg.group ? bundle.groups.find((item) => item.group === cfg.group) : null;

            return group ? group.table : bundle.standings.length ? bundle.standings : bundle.groups.flatMap((item) => item.table);
        }
        case 'fixtures':
            return matches.filter((match) => match.status !== 'finished').sort((a, b) => Number(b.status === 'live') - Number(a.status === 'live') || time(a) - time(b));
        case 'results':
            return matches.filter((match) => match.status === 'finished' || match.status === 'live').sort((a, b) => (cfg.round ? time(a) - time(b) : time(b) - time(a)));
        case 'scorers':
            return bundle.scorers;
        case 'discipline':
            return bundle.cards ?? [];
        case 'ticker': {
            const done = matches.filter((match) => match.status === 'finished' || match.status === 'live').sort((a, b) => time(b) - time(a)).slice(0, Math.ceil(cfg.rows / 2));
            const next = matches.filter((match) => match.status === 'scheduled').sort((a, b) => time(a) - time(b)).slice(0, Math.floor(cfg.rows / 2));

            return [...done.reverse(), ...next];
        }
        default:
            return [];
    }
}

/** Jogo da visão "Jogo em destaque": o pedido em `match`, senão o ao vivo, senão o próximo. */
export function obsFeaturedMatch(bundle: ObsBundle, cfg: ObsConfig): ObsMatch | null {
    const matches = bundle.matches.filter((match) => match.status !== 'cancelled');

    return matches.find((item) => item.id === cfg.match)
        ?? matches.find((item) => item.status === 'live')
        ?? matches.filter((item) => item.status === 'scheduled').sort((a, b) => time(a) - time(b))[0]
        ?? matches.filter((item) => item.status === 'finished').sort((a, b) => time(b) - time(a))[0]
        ?? null;
}

export function obsPageCount(bundle: ObsBundle, cfg: ObsConfig): number {
    if (cfg.view === 'ticker' || cfg.view === 'matchcard' || cfg.view === 'champion') return 1;

    return Math.max(1, Math.ceil(obsItems(bundle, cfg).length / cfg.rows));
}

export function obsTeam(bundle: ObsBundle, id: string | null, fallbackName: string): ObsTeam {
    return bundle.teams.find((team) => team.id === id) ?? { id: id ?? '', name: fallbackName, short: fallbackName.slice(0, 3).toUpperCase(), color: null, logo: null };
}

export function obsKickoff(match: ObsMatch): { day: string; time: string } {
    if (!match.kickoff_at) return { day: 'A DEFINIR', time: '--:--' };
    const [date, hour] = match.kickoff_at.split('T');
    const [, month, day] = date.split('-');

    return { day: `${day}/${month}`, time: (hour ?? '--:--').slice(0, 5) };
}

// ───────────── Modelos de estilo ─────────────

export const OBS_RESET: Partial<ObsConfig> = { accent: '', bg: '', text: '', bc: '', bw: 0, sh: 0, pad: 100, blur: 0, head: 1, dots: 1, surface: OBS_DEFAULTS.surface, radius: OBS_DEFAULTS.radius, scale: 100 };

export const OBS_TEMPLATES: { name: string; options: Partial<ObsConfig> }[] = [
    { name: 'Painel TV', options: { style: 'broadcast', anim: 'rise', accent: '', bg: '', text: '', surface: 94, radius: 10, bw: 0, sh: 0, blur: 0, pad: 100 } },
    { name: 'Vidro moderno', options: { style: 'glass', anim: 'fade', surface: 55, radius: 18, blur: 14, bw: 2, bc: '#ffffff', sh: 40, pad: 100 } },
    { name: 'Neon noturno', options: { style: 'neon', anim: 'zoom', accent: '#00e5ff', radius: 12, bw: 2, sh: 36, blur: 0, surface: 88 } },
    { name: 'Jornal claro', options: { style: 'light', anim: 'slide', accent: '#c1272d', radius: 6, bw: 0, sh: 30, surface: 100, pad: 90 } },
    { name: 'Barra esportiva', options: { style: 'stripe', anim: 'wipe', accent: '#2f7df6', radius: 0, bw: 0, sh: 24, surface: 96, pad: 85 } },
    { name: 'Minimal limpo', options: { style: 'minimal', anim: 'fade', radius: 4, bw: 0, sh: 0, surface: 60, pad: 110, head: 1 } },
    { name: 'Dourado premium', options: { style: 'gradient', anim: 'rise', accent: '#d8ad56', radius: 14, bw: 3, bc: '#d8ad56', sh: 50, surface: 92, pad: 105 } },
    { name: 'Compacto', options: { style: 'broadcast', anim: 'slide', radius: 8, pad: 60, scale: 90, dots: 0 } },
];

/** Aplica um modelo sobre a configuração atual e revalida tudo pela mesma regra dos parâmetros da URL. */
export function obsApplyStyle(cfg: ObsConfig, options: Record<string, unknown>, reset = true, fonts: string[] = []): ObsConfig {
    const next: Record<string, unknown> = { ...cfg, ...(reset ? OBS_RESET : {}) };

    for (const [key, value] of Object.entries(options)) {
        if ((OBS_STYLE_KEYS as readonly string[]).includes(key) && ['string', 'number', 'boolean'].includes(typeof value)) next[key] = value;
    }

    return obsConfig(obsQuery(next as unknown as ObsConfig), cfg.view, fonts);
}

/** Só as chaves de estilo (sem campeonato/visão): é o que um modelo guarda. */
export function obsStylePayload(cfg: ObsConfig): Record<string, string | number> {
    return Object.fromEntries(OBS_STYLE_KEYS.map((key) => [key, cfg[key] as string | number]));
}

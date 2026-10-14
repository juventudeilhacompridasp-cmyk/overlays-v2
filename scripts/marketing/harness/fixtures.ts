import type { LineupSide, OverlayAppearance, OverlayGame, OverlayState } from '@/lib/overlay';

// Dados 100 % fictícios (equipes, atletas e marcas inventados) para os prints da landing page.
const svg = (body: string, viewBox = '0 0 100 100') => `data:image/svg+xml;utf8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="${viewBox}">${body}</svg>`)}`;

export const crest = (color: string, tag: string) =>
    svg(`<path d="M10 8h80v46c0 24-22 36-40 42C32 90 10 78 10 54z" fill="${color}" stroke="#fff" stroke-width="4"/><text x="50" y="58" font-size="26" font-weight="900" text-anchor="middle" fill="#fff" font-family="Arial">${tag}</text>`);

export const avatar = (bg: string) =>
    svg(`<rect width="100" height="100" fill="${bg}"/><circle cx="50" cy="38" r="20" fill="#f1c9a5"/><path d="M12 100c0-24 17-36 38-36s38 12 38 36z" fill="#fff" opacity=".9"/>`);

export const banner = (bg: string, text: string, sub: string) =>
    svg(`<rect width="1500" height="200" fill="${bg}"/><rect x="0" y="0" width="1500" height="200" fill="url(#g)"/><defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#000" stop-opacity=".25"/></linearGradient></defs><text x="90" y="112" font-size="86" font-weight="900" fill="#fff" font-family="Arial">${text}</text><text x="92" y="158" font-size="34" fill="#fff" opacity=".85" font-family="Arial">${sub}</text>`, '0 0 1500 200');

export const HOME = { name: 'Leões da Ilha', short_name: 'LEO', logo_url: crest('#0d9488', 'LEO'), color: '#0d9488' };
export const AWAY = { name: 'Tigres FC', short_name: 'TIG', logo_url: crest('#ea580c', 'TIG'), color: '#ea580c' };

export const appearance: OverlayAppearance = {
    preset: 'arena', accent_color: '#14b8a6', background_color: '#0f172a', text_color: '#f8fafc', position: 'bottom-center', scale: 1, motion: 'none', layout: 'bar',
    scorers: true, font: 'system', show_badge: true, goal_celebration: false,
};

const now = () => Date.now();

export const game = (over: Partial<OverlayGame> = {}): OverlayGame => ({
    id: 'g1', status: 'in_progress', status_label: 'Em andamento', finished: false, home: HOME, away: AWAY, home_score: 2, away_score: 1, home_penalties: null, away_penalties: null,
    scheduled_at: null, venue: 'Estádio Municipal', competition: 'Copa Ilha', division: 'Principal', stage: 'Semifinal',
    clock: { state: 'running', period: 2, base_seconds: 67 * 60 + 12, running_since_ms: now() }, shot_clock: null, sport: 'football', score_model: 'goals', quarters: null, sets: null, fouls: null, timeouts: null, shootout: null,
    goals: [{ side: 'home', player: 'Rafael S.', minute: 23, own_goal: false }, { side: 'away', player: 'Diego L.', minute: 41, own_goal: false }, { side: 'home', player: 'Caio R.', minute: 58, own_goal: false }],
    ...over,
});

const names = ['Rafael Lima', 'Joãozinho', 'Renan Costa', 'Matheus Alves', 'Diego Souza', 'Pedro Henrique', 'Gabriel Nunes', 'Lucas Ferraz', 'Caio Ribeiro', 'Bruno Tavares', 'Thiago Melo'];
const slots: [string, string, number, number][] = [
    ['GOL', 'Goleiro', 50, 92], ['ZAG', 'Zagueiro', 15, 72], ['ZAG', 'Zagueiro', 38, 72], ['ZAG', 'Zagueiro', 62, 72], ['LD', 'Lateral direito', 85, 72],
    ['ME', 'Meia', 15, 46], ['VOL', 'Volante', 38, 46], ['VOL', 'Volante', 62, 46], ['MD', 'Meia', 85, 46], ['ATA', 'Atacante', 15, 20], ['ATA', 'Atacante', 85, 20],
];

export const lineupSide = (team: typeof HOME): LineupSide => ({
    name: team.name, logo_url: team.logo_url, color: team.color, formation: '4-4-2', formation_ready: true,
    starters: slots.map(([position, label, x, y], index) => ({ shirt: index === 0 ? 1 : index + 1, name: names[index], position, position_label: label, x, y, captain: index === 4, photo_url: avatar(team.color), photo_large_url: avatar(team.color) })),
    bench: [{ shirt: 12, name: 'Reserva Um', captain: false, photo_url: null, photo_large_url: null }],
    coach: 'Cléristón Barros',
});

export const state = (over: Partial<OverlayState> = {}): OverlayState => ({
    channel: 'principal', appearance, font_face: null,
    lineup_settings: { presentation: 'formation', list_seconds: 6, formation_seconds: 8, full_individuals: true, transition: 'fade', team: 'home', individual_mode: 'manual', individual_seconds: 6, individual_scope: 'starters', sponsor_banner: false, layout: 'classic', style: 'editorial', animation: 'slide', formation_mark: 'photo', formation_photo_size: 100, sponsor_size: 100, x: null, y: null, surface: 96, radius: null, text_scale: 100, step: 0, anchor_ms: 0 },
    sponsors: { enabled: true, seconds: 5, items: [] },
    lower_third_settings: { style: 'arena', animation: 'slide', seconds: 7, kinds: [], score: true, position: 'left', x: null, y: null, text_scale: 100, card_mode: 'banner' },
    layers: { lineup: true, standings: true, scoreboard: true, lower_third: true }, championship: {}, stoppage: 2, serve: null, social: { items: [], url: null },
    screens: { pregame: false, compare: false, cartela: false, summary: false, bracket: false, social: false, sponsor_bar: true, stats: false, shootout: true },
    stats: null, custom_live: {}, custom_table: null, cartela: { title: '', subtitle: '', until_ms: null }, summary: null, bracket: null, compare: null,
    game: game(), lineup: { home: lineupSide(HOME), away: lineupSide(AWAY) }, standings: null, server_time_ms: now(), ...over,
});

export const standings = () => ({
    title: 'Copa Ilha · Grupo A',
    rows: [
        ['Leões da Ilha', '#0d9488', 'LEO', 21, 9, 6], ['Tigres FC', '#ea580c', 'TIG', 19, 9, 4], ['Maré Alta', '#2563eb', 'MAR', 16, 9, 2], ['União do Porto', '#7c3aed', 'UNI', 14, 9, 1],
        ['Estrela do Sul', '#db2777', 'EST', 11, 9, -1], ['Vila Nova', '#ca8a04', 'VIL', 9, 9, -3], ['Atlético Costeiro', '#475569', 'ATL', 6, 9, -5], ['Real Pescadores', '#16a34a', 'REA', 3, 9, -8],
    ].map(([team, color, tag, points, played, gd], index) => ({
        position: index + 1, team: team as string, logo_url: crest(color as string, tag as string), points: points as number, played: played as number, goal_difference: gd as number,
        won: Math.floor((points as number) / 3), drawn: (points as number) % 3, lost: (played as number) - Math.floor((points as number) / 3) - ((points as number) % 3), color: color as string, highlight: index < 2,
    })),
});

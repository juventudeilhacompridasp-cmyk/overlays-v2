/**
 * Menu lateral do painel (código puro, testado em Node): quais itens cada pessoa vê e como se agrupam. A tela só desenha.
 * Grupos: Competição, Transmissão e conteúdo, Administração (só quem gerencia usuários) e Plataforma (só Super Admin).
 */
import type { FeatureKey } from '@/lib/can';

export type NavItem = { label: string; route: string; match: string; icon: string; feature?: FeatureKey };

export type NavGroupId = 'competition' | 'broadcast' | 'admin' | 'platform';

/** `area` marca os grupos de administração (visual separado); `defaultOpen` vale até a pessoa abrir/fechar. */
export type NavGroup = { id: NavGroupId; label: string; area: 'main' | 'admin' | 'platform'; defaultOpen: boolean; items: NavItem[] };

export type NavContext = {
    role: string | null;
    platformAdmin: boolean;
    features: string[];
    can: { competitions: boolean; operate: boolean; users: boolean; market: boolean };
};

export type NavMenu = { top: NavItem[]; groups: NavGroup[]; bottom: NavItem[] };

const ICONS = {
    home: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
    trophy: 'M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM5 4H3v2a3 3 0 003 3M19 4h2v2a3 3 0 01-3 3',
    team: 'M17 20h5v-2a4 4 0 00-5-3.9M9 11a4 4 0 100-8 4 4 0 000 8zM2 20v-2a4 4 0 014-4h6a4 4 0 014 4v2',
    person: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1',
    pin: 'M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11zM12 12a2 2 0 100-4 2 2 0 000 4z',
    camera: 'M3 7a2 2 0 012-2h9a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM15 10l6-3v10l-6-3',
    form: 'M9 12l2 2 4-4M7 4h10a2 2 0 012 2v14l-3-2-2 2-2-2-2 2-2-2-3 2V6a2 2 0 012-2z',
    chart: 'M9 17v-6M13 17V7M17 17v-4M4 4v16h16',
    code: 'M8 9l-4 3 4 3M16 9l4 3-4 3M13 6l-2 12',
    image: 'M4 16l4-4 4 4 3-3 5 5M4 5h16v14H4zM15 9h.01',
    phone: 'M12 18h.01M8 3h8a2 2 0 012 2v14a2 2 0 01-2 2H8a2 2 0 01-2-2V5a2 2 0 012-2z',
    users: 'M16 11a3 3 0 100-6 3 3 0 000 6zM8 11a3 3 0 100-6 3 3 0 000 6zM2 20v-1a4 4 0 014-4h4a4 4 0 014 4v1M15 15h3a4 4 0 014 4v1',
    shield: 'M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3zM9 12l2 2 4-4',
    sliders: 'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0M14 4v4M8 10v4M16 16v4',
    mail: 'M3 8l9 6 9-6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z',
    doc: 'M9 12h6M9 16h6M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z',
    globe: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a14 14 0 010 18M12 3a14 14 0 000 18',
    layers: 'M4 7l8-4 8 4-8 4-8-4zM4 12l8 4 8-4M4 17l8 4 8-4',
    pulse: 'M3 12h4l3-8 4 16 3-8h4',
    help: 'M9.1 9a3 3 0 115.8 1c0 2-3 2-3 4M12 17.5h.01M12 21a9 9 0 100-18 9 9 0 000 18z',
};

const item = (label: string, route: string, match: string, icon: string, feature?: FeatureKey): NavItem => ({ label, route, match, icon, ...(feature ? { feature } : {}) });

const managerNav: NavItem[] = [
    item('Início', 'manager.dashboard', 'manager.dashboard', ICONS.home),
    item('Jogos e escalações', 'manager.games.index', 'manager.games.*', 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z'),
    item('Mercado de jogadores', 'manager.market.index', 'manager.market.*', ICONS.team, 'market'),
];

const athleteNav: NavItem[] = [item('Meu perfil', 'athlete.dashboard', 'athlete.dashboard', ICONS.person)];

const has = (features: string[], entry: NavItem) => !entry.feature || features.includes(entry.feature);

/** Monta o menu da pessoa: itens soltos no topo, grupos recolhíveis e a Ajuda no fim. */
export function buildNav(ctx: NavContext): NavMenu {
    const helpArea = ctx.role === 'manager' ? 'manager' : ctx.role === 'athlete' ? 'athlete' : ctx.role ? 'panel' : 'platform';
    // Sem papel e sem ser Super Admin (ex.: só abriu o perfil) não há central de ajuda a abrir.
    const bottom = ctx.role || ctx.platformAdmin ? [item('Ajuda', `${helpArea}.help.index`, `${helpArea}.help.*`, ICONS.help)] : [];

    if (ctx.role === 'manager') return { top: managerNav.filter((entry) => has(ctx.features, entry)), groups: [], bottom };
    if (ctx.role === 'athlete') return { top: athleteNav, groups: [], bottom };

    const top = [item('Painel', 'panel.dashboard', 'panel.dashboard', ICONS.home)];

    if (ctx.can.operate) top.push(item('Mesário', 'panel.mesario', 'panel.mesario', ICONS.phone));

    const competition: NavItem[] = [item('Competições', 'panel.competitions.index', 'panel.competitions.*', ICONS.trophy)];

    if (ctx.can.competitions) competition.push(item('Inscrições', 'panel.registrations.index', 'panel.registrations.*', ICONS.form, 'online_registration'));

    competition.push(item('Equipes', 'panel.teams.index', 'panel.teams.*', ICONS.team), item('Atletas e comissão', 'panel.people.index', 'panel.people.*', ICONS.person), item('Estádios', 'panel.venues.index', 'panel.venues.*', ICONS.pin));

    if (ctx.can.competitions) competition.push(item('Relatórios', 'panel.reports.index', 'panel.reports.*', ICONS.chart, 'reports'));
    if (ctx.can.market) competition.push(item('Mercado de jogadores', 'panel.market.index', 'panel.market.*', ICONS.team, 'market'));

    const broadcast: NavItem[] = [item('Transmissão', 'panel.broadcast.index', 'panel.broadcast.*', ICONS.camera, 'overlays'), item('Artes', 'panel.arts.index', 'panel.arts.*', ICONS.image, 'arts')];

    if (ctx.can.competitions) broadcast.push(item('Widgets', 'panel.widgets.index', 'panel.widgets.*', ICONS.code, 'embeds'));

    const admin: NavItem[] = ctx.can.users
        ? [
              item('Usuários', 'panel.users.index', 'panel.users.*', ICONS.users),
              item('Perfis de acesso', 'panel.profiles.index', 'panel.profiles.*', ICONS.shield),
              item('Portal público', 'panel.organization.edit', 'panel.organization.*', ICONS.globe),
              item('Comunicações', 'panel.communications.edit', 'panel.communications.*', ICONS.mail),
              item('Notas da ajuda', 'panel.help-notes.index', 'panel.help-notes.*', ICONS.doc),
              item('Parâmetros', 'panel.parameters.edit', 'panel.parameters.*', ICONS.sliders),
              item('Termos e privacidade', 'panel.legal.edit', 'panel.legal.*', ICONS.doc),
          ]
        : [];

    const platform: NavItem[] = ctx.platformAdmin
        ? [
              item('Organizações', 'platform.organizations.index', 'platform.organizations.*', ICONS.layers),
              item('Status page', 'platform.status.index', 'platform.status.*', ICONS.pulse),
              item('Contatos da landing', 'platform.leads.index', 'platform.leads.*', ICONS.mail),
              item('Medição da landing', 'platform.metrics.index', 'platform.metrics.*', ICONS.chart),
              item('Documentação das APIs', 'platform.api-docs.index', 'platform.api-docs.*', ICONS.code),
          ]
        : [];

    const groups: NavGroup[] = [
        { id: 'competition', label: 'Competição', area: 'main', defaultOpen: true, items: competition },
        { id: 'broadcast', label: 'Transmissão e conteúdo', area: 'main', defaultOpen: true, items: broadcast },
        { id: 'admin', label: 'Administração', area: 'admin', defaultOpen: false, items: admin },
        { id: 'platform', label: 'Plataforma', area: 'platform', defaultOpen: false, items: platform },
    ];

    return {
        top: top.filter((entry) => has(ctx.features, entry)),
        groups: groups.map((group) => ({ ...group, items: group.items.filter((entry) => has(ctx.features, entry)) })).filter((group) => group.items.length > 0),
        bottom,
    };
}

/** Estado de abertura dos grupos: o que a pessoa escolheu; sem escolha vale o padrão; o grupo da página atual abre sempre. */
export function groupOpen(group: NavGroup, saved: Partial<Record<NavGroupId, boolean>>, activeGroup: NavGroupId | null): boolean {
    return group.id === activeGroup || (saved[group.id] ?? group.defaultOpen);
}

/** Grupo que contém a rota atual (`isActive` recebe o `match` do item). */
export function activeGroupOf(menu: NavMenu, isActive: (match: string) => boolean): NavGroupId | null {
    return menu.groups.find((group) => group.items.some((entry) => isActive(entry.match)))?.id ?? null;
}

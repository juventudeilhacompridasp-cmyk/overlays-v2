import { Link, usePage } from '@inertiajs/react';
import ThemeToggle from '@/Components/ThemeToggle';
import { FeatureKey, useCan } from '@/lib/can';
import { registerServiceWorker, useOnline } from '@/lib/pwa';
import { PropsWithChildren, useEffect, useState } from 'react';

type NavItem = { label: string; route: string; match: string; icon: string; feature?: FeatureKey };

const nav: NavItem[] = [
    { label: 'Painel', route: 'panel.dashboard', match: 'panel.dashboard', icon: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10' },
    { label: 'Competições', route: 'panel.competitions.index', match: 'panel.competitions.*', icon: 'M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM5 4H3v2a3 3 0 003 3M19 4h2v2a3 3 0 01-3 3' },
    { label: 'Equipes', route: 'panel.teams.index', match: 'panel.teams.*', icon: 'M17 20h5v-2a4 4 0 00-5-3.9M9 11a4 4 0 100-8 4 4 0 000 8zM2 20v-2a4 4 0 014-4h6a4 4 0 014 4v2' },
    { label: 'Atletas e comissão', route: 'panel.people.index', match: 'panel.people.*', icon: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1' },
    { label: 'Estádios', route: 'panel.venues.index', match: 'panel.venues.*', icon: 'M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11zM12 12a2 2 0 100-4 2 2 0 000 4z' },
    { label: 'Transmissão', route: 'panel.broadcast.index', match: 'panel.broadcast.*', feature: 'overlays', icon: 'M3 7a2 2 0 012-2h9a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM15 10l6-3v10l-6-3' },
];

const registrationsNav: NavItem = { feature: 'online_registration', label: 'Inscrições', route: 'panel.registrations.index', match: 'panel.registrations.*', icon: 'M9 12l2 2 4-4M7 4h10a2 2 0 012 2v14l-3-2-2 2-2-2-2 2-2-2-3 2V6a2 2 0 012-2z' };

const reportsNav: NavItem = { feature: 'reports', label: 'Relatórios', route: 'panel.reports.index', match: 'panel.reports.*', icon: 'M9 17v-6M13 17V7M17 17v-4M4 4v16h16' };

const widgetsNav: NavItem = { label: 'Widgets', route: 'panel.widgets.index', match: 'panel.widgets.*', feature: 'embeds', icon: 'M8 9l-4 3 4 3M16 9l4 3-4 3M13 6l-2 12' };

const mesarioNav: NavItem = { label: 'Mesário', route: 'panel.mesario', match: 'panel.mesario', icon: 'M12 18h.01M8 3h8a2 2 0 012 2v14a2 2 0 01-2 2H8a2 2 0 01-2-2V5a2 2 0 012-2z' };

// Gestor de equipe (ADR 0003): menu próprio, sem nada do painel do organizador.
const managerNav: NavItem[] = [
    { label: 'Início', route: 'manager.dashboard', match: 'manager.dashboard', icon: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10' },
    { label: 'Jogos e escalações', route: 'manager.games.index', match: 'manager.games.*', icon: 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z' },
];

const platformNav: NavItem = { label: 'Plataforma', route: 'platform.organizations.index', match: 'platform.*', icon: 'M4 7l8-4 8 4-8 4-8-4zM4 12l8 4 8-4M4 17l8 4 8-4' };

const adminNav: NavItem[] = [
    { label: 'Termos e privacidade', route: 'panel.legal.edit', match: 'panel.legal.*', icon: 'M9 12h6M9 16h6M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z' },
    { label: 'Portal público', route: 'panel.organization.edit', match: 'panel.organization.*', icon: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a14 14 0 010 18M12 3a14 14 0 000 18' },
];

const usersNav: NavItem = { label: 'Usuários', route: 'panel.users.index', match: 'panel.users.*', icon: 'M16 11a3 3 0 100-6 3 3 0 000 6zM8 11a3 3 0 100-6 3 3 0 000 6zM2 20v-1a4 4 0 014-4h4a4 4 0 014 4v1M15 15h3a4 4 0 014 4v1' };

function Icon({ path }: { path: string }) {
    return (
        <svg className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d={path} />
        </svg>
    );
}

export default function PanelLayout({ children }: PropsWithChildren) {
    const { auth, flash, brand } = usePage().props;
    const [open, setOpen] = useState(false);
    const canManageUsers = useCan('manage-users');
    const online = useOnline();

    useEffect(() => {
        registerServiceWorker();
    }, []);
    const canManageCompetitions = useCan('manage-competitions');
    const canOperate = useCan('operate-games');
    const base = canManageCompetitions ? [...nav.slice(0, 2), registrationsNav, ...nav.slice(2), reportsNav, widgetsNav] : nav;
    const { features } = usePage().props;
    const withMesario = canOperate ? [nav[0], mesarioNav, ...base.slice(1)] : base;
    const all = canManageUsers ? [...withMesario, ...adminNav, usersNav] : withMesario;
    const withPlatform = auth.platform_admin ? [...all, platformNav] : all;
    const items = auth.role === 'manager' ? managerNav : withPlatform.filter((item) => !item.feature || features.includes(item.feature));

    const menu = (
        <nav className="flex flex-col gap-1 px-3">
            {items.map((item) => {
                const active = route().current(item.match);

                return (
                    <Link
                        key={item.route}
                        href={route(item.route)}
                        onClick={() => setOpen(false)}
                        className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium ${
                            active ? 'bg-emerald-50 text-emerald-800' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                        }`}
                    >
                        <Icon path={item.icon} />
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-screen bg-gray-50 print:bg-[#ffffff]">
            <aside className="fixed inset-y-0 left-0 hidden w-60 flex-col border-r border-gray-200 bg-white py-5 lg:flex print:hidden">
                <div className="mb-6 flex items-center gap-2 px-6 text-lg font-bold text-emerald-700">
                    {brand.logo_url && <img src={brand.logo_url} alt="" className="h-8 w-8 rounded bg-[#ffffff] object-contain p-0.5" />}
                    <span className="truncate">{brand.name}</span>
                </div>
                {menu}
                <div className="mt-auto border-t border-gray-100 px-6 pt-4 text-sm">
                    <div className="mb-3">
                        <ThemeToggle />
                    </div>
                    <div className="truncate font-medium text-gray-800">{auth.user.name}</div>
                    {auth.role_label && <div className="text-xs text-gray-500">{auth.role_label}</div>}
                    <div className="mt-1 flex gap-3 text-gray-500">
                        <Link href={route('profile.edit')} className="hover:text-emerald-700">
                            Perfil
                        </Link>
                        <Link href={route('logout')} method="post" as="button" className="hover:text-emerald-700">
                            Sair
                        </Link>
                    </div>
                </div>
            </aside>

            <div className="lg:pl-60 print:pl-0">
                <header className="sticky top-0 z-30 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 lg:hidden print:hidden">
                    <span className="font-bold text-emerald-700">{brand.name}</span>
                    <ThemeToggle />
                    <button type="button" onClick={() => setOpen(!open)} className="rounded-md p-2 text-gray-600 hover:bg-gray-100" aria-label="Menu">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </header>
                {open && <div className="border-b border-gray-200 bg-white py-3 lg:hidden">{menu}</div>}

                {!online && (
                    <div role="alert" className="bg-amber-500 px-4 py-2 text-center text-sm font-medium text-black print:hidden" data-testid="offline-banner">
                        Sem conexão. Na súmula os lançamentos ficam guardados neste aparelho e são enviados quando a internet voltar; nas demais telas, o que for salvo agora não chega ao servidor.
                    </div>
                )}
                <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                    {flash?.success && (
                        <div role="status" className="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div role="alert" className="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                            {flash.error}
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}

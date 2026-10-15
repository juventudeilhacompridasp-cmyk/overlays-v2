import { Link, usePage } from '@inertiajs/react';
import FlashToasts from '@/Components/FlashToasts';
import HelpButton from '@/Components/HelpButton';
import ThemeToggle from '@/Components/ThemeToggle';
import { useCan } from '@/lib/can';
import { activeGroupOf, buildNav, groupOpen, NavGroup, NavGroupId, NavItem } from '@/lib/panelNav';
import { registerServiceWorker, useOnline } from '@/lib/pwa';
import { PropsWithChildren, useEffect, useState } from 'react';

const NAV_KEY = 'panel:nav-groups';

function Icon({ path }: { path: string }) {
    return (
        <svg className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d={path} />
        </svg>
    );
}

export default function PanelLayout({ children }: PropsWithChildren) {
    const { auth, brand } = usePage().props;
    const [open, setOpen] = useState(false);
    const canManageUsers = useCan('manage-users');
    const online = useOnline();

    useEffect(() => {
        registerServiceWorker();
    }, []);
    const canManageCompetitions = useCan('manage-competitions');
    const canOperate = useCan('operate-games');
    const canModerateMarket = useCan('manage-market');
    const { features } = usePage().props;
    const menuData = buildNav({ role: auth.role ?? null, platformAdmin: auth.platform_admin === true, features, can: { competitions: canManageCompetitions, operate: canOperate, users: canManageUsers, market: canModerateMarket } });
    const activeGroup = activeGroupOf(menuData, (match) => route().current(match));
    // Grupos abertos/fechados pela pessoa ficam neste navegador; o grupo da página atual abre sempre.
    const [saved, setSaved] = useState<Partial<Record<NavGroupId, boolean>>>(() => {
        try {
            return JSON.parse(window.localStorage.getItem(NAV_KEY) ?? '{}') ?? {};
        } catch {
            return {};
        }
    });
    const toggleGroup = (group: NavGroup) => {
        const next = { ...saved, [group.id]: !groupOpen(group, saved, activeGroup) };
        setSaved(next);

        try {
            window.localStorage.setItem(NAV_KEY, JSON.stringify(next));
        } catch {
            /* sem armazenamento: vale só nesta visita */
        }
    };

    const link = (entry: NavItem, nested = false) => {
        const active = route().current(entry.match);

        return (
            <Link
                key={entry.route}
                href={route(entry.route)}
                onClick={() => setOpen(false)}
                aria-current={active ? 'page' : undefined}
                className={`flex items-center gap-3 rounded-md py-2 pr-3 text-sm font-medium ${nested ? 'pl-5' : 'pl-3'} ${active ? 'bg-emerald-50 text-emerald-800' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'}`}
            >
                <Icon path={entry.icon} />
                {entry.label}
            </Link>
        );
    };

    const menu = (
        <nav className="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 pb-2" aria-label="Menu principal">
            {menuData.top.map((entry) => link(entry))}
            {menuData.groups.map((group) => {
                const expanded = groupOpen(group, saved, activeGroup);
                const separated = group.area !== 'main';

                return (
                    <div key={group.id} className={separated ? 'mt-2 border-t border-gray-100 pt-2' : 'mt-1'} data-nav-group={group.id} data-area={group.area}>
                        <button type="button" onClick={() => toggleGroup(group)} aria-expanded={expanded} aria-controls={`nav-${group.id}`} className="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-gray-400 hover:bg-gray-50 hover:text-gray-600">
                            <span className="flex items-center gap-1.5">
                                {separated && <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.2} aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M7 11V8a5 5 0 0110 0v3M6 11h12v9H6z" /></svg>}
                                {group.label}
                            </span>
                            <svg className={`h-3.5 w-3.5 transition-transform ${expanded ? 'rotate-90' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.4} aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M9 6l6 6-6 6" /></svg>
                        </button>
                        {expanded && <div id={`nav-${group.id}`} className="mt-0.5 flex flex-col gap-0.5">{group.items.map((entry) => link(entry, true))}</div>}
                    </div>
                );
            })}
            {menuData.bottom.length > 0 && <div className="mt-2 border-t border-gray-100 pt-2">{menuData.bottom.map((entry) => link(entry))}</div>}
        </nav>
    );

    return (
        <div className="min-h-screen bg-gray-50 print:bg-[#ffffff]">
            <aside className="fixed inset-y-0 left-0 hidden w-60 flex-col border-r border-gray-200 bg-white py-5 lg:flex print:hidden">
                <div className="mb-6 flex shrink-0 items-center gap-2 px-6 text-lg font-bold text-emerald-700">
                    {brand.logo_url && <img src={brand.logo_url} alt="" className="h-8 w-8 rounded bg-[#ffffff] object-contain p-0.5" />}
                    <span className="truncate">{brand.name}</span>
                </div>
                {menu}
                <div className="shrink-0 border-t border-gray-100 px-6 pt-4 text-sm">
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
                {open && <div className="flex max-h-[calc(100vh-4rem)] flex-col border-b border-gray-200 bg-white py-3 lg:hidden">{menu}</div>}

                {!online && (
                    <div role="alert" className="bg-amber-500 px-4 py-2 text-center text-sm font-medium text-black print:hidden" data-testid="offline-banner">
                        Sem conexão. Na súmula os lançamentos ficam guardados neste aparelho e são enviados quando a internet voltar; nas demais telas, o que for salvo agora não chega ao servidor.
                    </div>
                )}
                <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                    <FlashToasts />
                    {children}
                </main>
                <HelpButton />
            </div>
        </div>
    );
}

import { useCan } from '@/lib/can';
import { buildNav, quickSections } from '@/lib/panelNav';
import { Link, usePage } from '@inertiajs/react';

/** Acesso rápido do início: um cartão grande por área, o mesmo conjunto do menu (papel e pacote), pensado para o toque. */
export default function QuickCards() {
    const { auth, features } = usePage().props;
    const canCompetitions = useCan('manage-competitions');
    const canOperate = useCan('operate-games');
    const canUsers = useCan('manage-users');
    const canMarket = useCan('manage-market');
    const sections = quickSections(
        buildNav({ role: auth.role ?? null, platformAdmin: auth.platform_admin === true, features, can: { competitions: canCompetitions, operate: canOperate, users: canUsers, market: canMarket } }),
    );

    return (
        <nav aria-label="Acesso rápido" className="mb-8 space-y-6" data-testid="quick-cards">
            {sections.map((section) => (
                <section key={section.id} aria-label={section.title ?? 'Atalhos'}>
                    {section.title && (
                        <h2 className="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-gray-400">
                            {section.area !== 'main' && (
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.2} aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M7 11V8a5 5 0 0110 0v3M6 11h12v9H6z" />
                                </svg>
                            )}
                            {section.title}
                        </h2>
                    )}
                    <ul className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                        {section.items.map((entry) => (
                            <li key={entry.route}>
                                <Link
                                    href={route(entry.route)}
                                    className="flex h-full min-h-28 flex-col gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 transition hover:ring-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 active:scale-[0.98]"
                                >
                                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8} aria-hidden="true">
                                            <path strokeLinecap="round" strokeLinejoin="round" d={entry.icon} />
                                        </svg>
                                    </span>
                                    <span className="font-semibold leading-tight text-gray-900">{entry.label}</span>
                                    {entry.hint && <span className="text-xs leading-snug text-gray-500">{entry.hint}</span>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </nav>
    );
}

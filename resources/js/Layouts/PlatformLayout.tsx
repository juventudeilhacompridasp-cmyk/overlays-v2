import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

/** Layout da área da plataforma (/plataforma): independe de papel em uma organização. */
export default function PlatformLayout({ children }: PropsWithChildren) {
    const { auth, flash } = usePage().props;

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <Link href={route('platform.organizations.index')} className="font-bold text-emerald-700">
                        Plataforma · administração
                    </Link>
                    <nav className="flex items-center gap-4 text-sm text-gray-600">
                        <Link href={route('panel.dashboard')} className="hover:text-emerald-700">
                            Ir para o painel
                        </Link>
                        <ThemeToggle />
                        <span>{auth.user.name}</span>
                        <Link href={route('logout')} method="post" as="button" className="hover:text-emerald-700">
                            Sair
                        </Link>
                    </nav>
                </div>
            </header>
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
    );
}

import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

/** Telas de acesso (entrar, recuperar senha...): cartão central com a marca da organização/plataforma. */
export default function Guest({
    children,
    title,
    subtitle,
}: PropsWithChildren<{ title?: string; subtitle?: ReactNode }>) {
    const { brand } = usePage().props;

    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-10">
            <div className="absolute right-4 top-4">
                <ThemeToggle />
            </div>

            <Link href="/" className="mb-6 flex items-center gap-3 text-xl font-bold text-emerald-700">
                {brand.logo_url ? (
                    <img src={brand.logo_url} alt="" className="h-10 w-10 rounded bg-[#ffffff] object-contain p-0.5" />
                ) : (
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-600 text-lg text-white">
                        {brand.name.slice(0, 1).toUpperCase()}
                    </span>
                )}
                <span>{brand.name}</span>
            </Link>

            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                {title && <h1 className="text-xl font-bold text-gray-900">{title}</h1>}
                {subtitle && <p className="mt-1 text-sm text-gray-600">{subtitle}</p>}
                <div className={title ? 'mt-6' : ''}>{children}</div>
            </div>
            <nav className="mt-6 flex flex-wrap justify-center gap-x-4 gap-y-2 text-sm text-gray-600" aria-label="Links públicos">
                <Link href={route('portal.home')} className="hover:text-emerald-700 hover:underline">Voltar ao portal</Link>
                <a href={route('marketing.landing')} className="hover:text-emerald-700 hover:underline">Conheça a plataforma</a>
                <Link href={route('portal.terms')} className="hover:text-emerald-700 hover:underline">Termos</Link>
                <Link href={route('portal.privacy')} className="hover:text-emerald-700 hover:underline">Privacidade</Link>
            </nav>
        </div>
    );
}

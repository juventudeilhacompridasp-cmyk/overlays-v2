import { Head, Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export interface Theme {
    name: string;
    primary: string;
    tagline: string | null;
}

export interface Seo {
    title: string;
    description: string;
    url: string;
    image: string | null;
}

/** Título, descrição, canonical e Open Graph: renderizados no servidor (SSR) para buscadores e pré-visualização de links. */
export function SeoHead({ seo }: { seo: Seo }) {
    return (
        <Head title={seo.title}>
            <meta name="description" content={seo.description} />
            <link rel="canonical" href={seo.url} />
            <meta property="og:type" content="website" />
            <meta property="og:locale" content="pt_BR" />
            <meta property="og:title" content={seo.title} />
            <meta property="og:description" content={seo.description} />
            <meta property="og:url" content={seo.url} />
            {seo.image && <meta property="og:image" content={seo.image} />}
            <meta name="twitter:card" content={seo.image ? 'summary_large_image' : 'summary'} />
        </Head>
    );
}

export default function PortalLayout({ theme, seo, children }: PropsWithChildren<{ theme: Theme; seo: Seo }>) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-screen bg-gray-50 text-gray-900" style={{ ['--brand' as string]: theme.primary }}>
            <SeoHead seo={seo} />
            <header className="text-white print:hidden" style={{ backgroundColor: theme.primary }}>
                <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                    <Link href={route('portal.home')} className="text-xl font-extrabold tracking-tight">
                        {theme.name}
                    </Link>
                    <nav className="flex items-center gap-4 text-sm font-medium">
                        <Link href={route('portal.home')} className="hover:underline">
                            Competições
                        </Link>
                        {auth.user ? (
                            <Link href={route('panel.dashboard')} className="rounded bg-white/20 px-3 py-1 hover:bg-white/30">
                                Painel
                            </Link>
                        ) : (
                            <Link href={route('login')} className="rounded bg-white/20 px-3 py-1 hover:bg-white/30">
                                Entrar
                            </Link>
                        )}
                    </nav>
                </div>
                {theme.tagline && <div className="mx-auto max-w-5xl px-4 pb-4 text-sm text-white/90">{theme.tagline}</div>}
            </header>
            <main className="mx-auto max-w-5xl px-4 py-8">{children}</main>
            <footer className="mx-auto max-w-5xl px-4 pb-10 text-xs text-gray-500 print:hidden">
                {theme.name} · Resultados e classificações atualizados pela organização.
            </footer>
        </div>
    );
}

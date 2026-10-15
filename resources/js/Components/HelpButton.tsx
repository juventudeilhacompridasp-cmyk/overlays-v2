import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Ajuda desta tela (ADR 0006, parte 2): botão "?" fixo no canto que leva ao artigo ligado à rota atual. Com mais de um artigo,
 * abre uma lista curta. Some quando não há artigo para a tela e na impressão.
 */
export default function HelpButton() {
    const { help } = usePage().props;
    const [open, setOpen] = useState(false);

    if (!help || help.articles.length === 0) return null;

    const [first] = help.articles;

    return (
        <div className="fixed bottom-4 right-4 z-30 print:hidden" data-testid="contextual-help">
            {open && help.articles.length > 1 && (
                <ul className="mb-2 w-64 space-y-1 rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-lg" role="menu">
                    {help.articles.map((article) => (
                        <li key={article.slug} role="none">
                            <Link href={article.url} role="menuitem" className="block rounded px-2 py-1.5 text-gray-700 hover:bg-gray-100 hover:text-emerald-700">
                                {article.title}
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
            {help.articles.length === 1 ? (
                <Link href={first.url} aria-label={`Ajuda desta tela: ${first.title}`} title={`Ajuda desta tela: ${first.title}`} className="grid h-10 w-10 place-items-center rounded-full bg-emerald-600 text-lg font-bold text-white shadow-lg hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                    ?
                </Link>
            ) : (
                <button type="button" aria-expanded={open} aria-label="Ajuda desta tela" title="Ajuda desta tela" onClick={() => setOpen(!open)} className="grid h-10 w-10 place-items-center rounded-full bg-emerald-600 text-lg font-bold text-white shadow-lg hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                    ?
                </button>
            )}
        </div>
    );
}

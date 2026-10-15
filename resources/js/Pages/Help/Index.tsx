import { Card, Empty, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Summary {
    slug: string;
    title: string;
    category: string;
    snippet: string;
}

interface Props {
    area: string;
    routes: { index: string; show: string };
    q: string;
    results: Summary[];
    categories: { name: string; articles: Summary[] }[];
    feedback_summary?: { slug: string; title: string; yes: number; no: number }[];
}

export default function Index({ routes, q, results, categories, feedback_summary }: Props) {
    const [term, setTerm] = useState(q);

    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(route(routes.index), term.trim() === '' ? {} : { q: term.trim() }, { preserveState: true });
    };

    return (
        <PanelLayout>
            <Head title="Ajuda" />
            <PageHeader title="Central de ajuda" subtitle="Como configurar, operar e tirar dúvidas. Só aparecem os assuntos que o seu acesso e o pacote da organização permitem." />

            <form onSubmit={search} className="mb-6 flex max-w-2xl gap-2" role="search">
                <input
                    type="search"
                    value={term}
                    onChange={(e) => setTerm(e.target.value)}
                    placeholder="O que você quer saber? Ex.: lançar gol, convidar gestor, avisos por e-mail"
                    aria-label="Buscar na ajuda"
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
                <button type="submit" className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Buscar
                </button>
            </form>

            {q !== '' ? (
                results.length === 0 ? (
                    <Empty title="Nada encontrado">
                        Não achei artigos para “{q}”. Tente outras palavras ou <Link href={route(routes.index)} className="text-emerald-700 underline">veja todos os assuntos</Link>.
                    </Empty>
                ) : (
                    <Card title={`${results.length} resultado(s) para “${q}”`} className="max-w-3xl">
                        <ul className="divide-y divide-gray-100" data-testid="results">
                            {results.map((hit) => (
                                <li key={hit.slug} className="py-3">
                                    <Link href={route(routes.show, hit.slug)} className="font-medium text-emerald-700 hover:underline">
                                        {hit.title}
                                    </Link>
                                    <div className="text-xs text-gray-500">{hit.category}</div>
                                    <p className="mt-1 text-sm text-gray-700">{hit.snippet}</p>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )
            ) : categories.length === 0 ? (
                <Empty title="Nenhum artigo disponível">Ainda não há ajuda para o seu acesso.</Empty>
            ) : (
                <div className="grid max-w-5xl gap-4 md:grid-cols-2" data-testid="categories">
                    {categories.map((category) => (
                        <Card key={category.name} title={category.name}>
                            <ul className="space-y-3">
                                {category.articles.map((article) => (
                                    <li key={article.slug}>
                                        <Link href={route(routes.show, article.slug)} className="font-medium text-emerald-700 hover:underline">
                                            {article.title}
                                        </Link>
                                        {article.snippet && <p className="text-sm text-gray-600">{article.snippet}</p>}
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    ))}
                </div>
            )}
            {feedback_summary && feedback_summary.length > 0 && (
                <Card title="Artigos que mais precisam de ajuste (votos “não ajudou”)" className="mt-6 max-w-3xl">
                    <ul className="divide-y divide-gray-100 text-sm" data-testid="feedback-summary">
                        {feedback_summary.map((item) => (
                            <li key={item.slug} className="flex items-center justify-between gap-3 py-2">
                                <Link href={route(routes.show, item.slug)} className="text-emerald-700 hover:underline">
                                    {item.title}
                                </Link>
                                <span className="shrink-0 text-gray-500">
                                    {item.no} não · {item.yes} sim
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </PanelLayout>
    );
}

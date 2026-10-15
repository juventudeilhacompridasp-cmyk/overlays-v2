import { Card, Empty, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const xsrf = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

interface Answer {
    answer: string | null;
    sources: { slug: string; title: string; url: string }[];
    remaining: number;
    message: string | null;
}

/** "Perguntar à ajuda": resposta com IA só a partir dos artigos; sempre mostra as fontes e avisa que é gerada por IA. */
function AskBox({ ai }: { ai: { ask: string; remaining: number; max_length: number } }) {
    const [question, setQuestion] = useState('');
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState<Answer | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [remaining, setRemaining] = useState(ai.remaining);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setError(null);
        setResult(null);

        try {
            const response = await fetch(route(ai.ask), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
                body: JSON.stringify({ question }),
            });
            const body = (await response.json()) as Partial<Answer> & { message?: string; errors?: Record<string, string[]> };

            if (typeof body.remaining === 'number') setRemaining(body.remaining);

            if (!response.ok) setError(body.errors ? Object.values(body.errors)[0][0] : (body.message ?? 'Não foi possível perguntar agora.'));
            else setResult(body as Answer);
        } catch {
            setError('Sem conexão. Tente de novo.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Card title="Perguntar à ajuda" className="mb-6 max-w-2xl">
            <form onSubmit={submit} className="space-y-2">
                <textarea value={question} onChange={(event) => setQuestion(event.target.value)} maxLength={ai.max_length} rows={2} aria-label="Sua pergunta" placeholder="Escreva a dúvida com suas palavras. Não inclua dados pessoais." className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                <div className="flex flex-wrap items-center gap-3">
                    <button type="submit" disabled={busy || remaining === 0 || question.trim().length < 5} className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">{busy ? 'Perguntando…' : 'Perguntar'}</button>
                    <span className="text-xs text-gray-500">{remaining} pergunta(s) restante(s) hoje</span>
                </div>
            </form>
            {error && <p role="alert" className="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
            {result && (
                <div className="mt-3 text-sm" data-testid="ai-answer">
                    {result.answer ? (
                        <>
                            <p className="whitespace-pre-line text-gray-800">{result.answer}</p>
                            <p className="mt-2 text-xs text-gray-500">Resposta gerada por IA a partir dos artigos abaixo. Confira nos artigos antes de agir.</p>
                            <ul className="mt-1 list-inside list-disc text-xs">
                                {result.sources.map((source) => (
                                    <li key={source.slug}><Link href={source.url} className="text-emerald-700 underline">{source.title}</Link></li>
                                ))}
                            </ul>
                        </>
                    ) : (
                        <p className="text-gray-700">{result.message}</p>
                    )}
                </div>
            )}
        </Card>
    );
}

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
    ai?: { ask: string; remaining: number; max_length: number } | null;
}

export default function Index({ routes, q, results, categories, feedback_summary, ai }: Props) {
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

            {ai && <AskBox ai={ai} />}

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

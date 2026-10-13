import { Badge, Card, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

type Inline = { t: 'text' | 'b' | 'code' | 'link'; v: string; href?: string };

type Block =
    | { type: 'h2' | 'h3'; text: string }
    | { type: 'p' | 'note'; inline: Inline[] }
    | { type: 'ul' | 'ol'; items: Inline[][] };

interface Props {
    area: string;
    routes: { index: string; show: string };
    article: { slug: string; title: string; category: string; tags: string[]; blocks: Block[]; screen_url: string | null };
    related: { slug: string; title: string; category: string }[];
}

function Text({ parts }: { parts: Inline[] }): ReactNode {
    return (
        <>
            {parts.map((part, index) => {
                if (part.t === 'b') return <strong key={index}>{part.v}</strong>;
                if (part.t === 'code') return <code key={index} className="rounded bg-gray-100 px-1 text-[0.85em]">{part.v}</code>;
                if (part.t === 'link' && part.href) {
                    return (
                        <Link key={index} href={part.href} className="text-emerald-700 underline">
                            {part.v}
                        </Link>
                    );
                }
                return <span key={index}>{part.v}</span>;
            })}
        </>
    );
}

export default function Show({ routes, article, related }: Props) {
    return (
        <PanelLayout>
            <Head title={`${article.title} · Ajuda`} />
            <PageHeader
                title={article.title}
                subtitle={article.category}
                breadcrumbs={[{ label: 'Ajuda', href: route(routes.index) }, { label: article.title }]}
                actions={article.screen_url ? <LinkButton href={article.screen_url}>Abrir esta tela</LinkButton> : undefined}
            />

            <article className="max-w-3xl space-y-3 text-sm leading-6 text-gray-800" data-testid="article">
                {article.blocks.map((block, index) => {
                    switch (block.type) {
                        case 'h2':
                            return (
                                <h2 key={index} className="pt-3 text-lg font-semibold text-gray-900">
                                    {block.text}
                                </h2>
                            );
                        case 'h3':
                            return (
                                <h3 key={index} className="pt-2 font-semibold text-gray-900">
                                    {block.text}
                                </h3>
                            );
                        case 'note':
                            return (
                                <p key={index} className="rounded-md border-l-4 border-amber-400 bg-amber-50 p-3 text-amber-900">
                                    <Text parts={block.inline} />
                                </p>
                            );
                        case 'ul':
                            return (
                                <ul key={index} className="list-disc space-y-1 pl-6">
                                    {block.items.map((item, i) => (
                                        <li key={i}>
                                            <Text parts={item} />
                                        </li>
                                    ))}
                                </ul>
                            );
                        case 'ol':
                            return (
                                <ol key={index} className="list-decimal space-y-1 pl-6">
                                    {block.items.map((item, i) => (
                                        <li key={i}>
                                            <Text parts={item} />
                                        </li>
                                    ))}
                                </ol>
                            );
                        default:
                            return (
                                <p key={index}>
                                    <Text parts={block.inline} />
                                </p>
                            );
                    }
                })}
            </article>

            {article.tags.length > 0 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {article.tags.map((tag) => (
                        <Link key={tag} href={route(routes.index, { q: tag })}>
                            <Badge tone="gray">{tag}</Badge>
                        </Link>
                    ))}
                </div>
            )}

            {related.length > 0 && (
                <Card title="Veja também" className="mt-6 max-w-3xl">
                    <ul className="space-y-1 text-sm">
                        {related.map((item) => (
                            <li key={item.slug}>
                                <Link href={route(routes.show, item.slug)} className="text-emerald-700 hover:underline">
                                    {item.title}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </PanelLayout>
    );
}

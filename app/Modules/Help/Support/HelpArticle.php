<?php

namespace App\Modules\Help\Support;

/** Um artigo da central de ajuda (arquivo Markdown em `resources/help/{público}/{slug}.md`). */
final class HelpArticle
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $audience,
        public readonly string $title,
        public readonly string $category,
        public readonly array $tags,
        public readonly int $order,
        public readonly ?string $ability,
        public readonly ?string $feature,
        public readonly ?string $route,
        public readonly string $body,
    ) {}
}

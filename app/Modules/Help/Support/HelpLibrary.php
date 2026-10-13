<?php

namespace App\Modules\Help\Support;

use Illuminate\Support\Facades\File;

/**
 * Lê os artigos de ajuda versionados no repositório. Cada arquivo começa com um cabeçalho `---` (title, category, tags,
 * order, ability, feature, route) seguido do texto em Markdown simples. O público é a pasta: panel, manager ou platform.
 */
final class HelpLibrary
{
    public const AUDIENCES = ['panel', 'manager', 'athlete', 'platform'];

    /** @var list<HelpArticle>|null */
    private static ?array $articles = null;

    /**
     * @return list<HelpArticle>
     */
    public static function all(): array
    {
        if (self::$articles !== null) {
            return self::$articles;
        }

        $articles = [];

        foreach (self::AUDIENCES as $audience) {
            foreach (File::glob(resource_path("help/{$audience}/*.md")) ?: [] as $path) {
                $article = self::parse($audience, pathinfo($path, PATHINFO_FILENAME), (string) file_get_contents($path));

                if ($article !== null) {
                    $articles[] = $article;
                }
            }
        }

        usort($articles, fn (HelpArticle $a, HelpArticle $b) => [$a->category, $a->order, $a->title] <=> [$b->category, $b->order, $b->title]);

        return self::$articles = $articles;
    }

    public static function find(string $slug): ?HelpArticle
    {
        foreach (self::all() as $article) {
            if ($article->slug === $slug) {
                return $article;
            }
        }

        return null;
    }

    /** Limpa a memória (testes). */
    public static function flush(): void
    {
        self::$articles = null;
    }

    public static function parse(string $audience, string $slug, string $content): ?HelpArticle
    {
        if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $content, $match)) {
            return null;
        }

        $meta = [];

        foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $meta[trim($key)] = trim($value);
            }
        }

        if (($meta['title'] ?? '') === '') {
            return null;
        }

        return new HelpArticle(
            slug: $slug,
            audience: $audience,
            title: $meta['title'],
            category: $meta['category'] ?? 'Geral',
            tags: array_values(array_filter(array_map('trim', explode(',', $meta['tags'] ?? '')))),
            order: (int) ($meta['order'] ?? 100),
            ability: ($meta['ability'] ?? '') ?: null,
            feature: ($meta['feature'] ?? '') ?: null,
            route: ($meta['route'] ?? '') ?: null,
            body: trim($match[2]),
        );
    }
}

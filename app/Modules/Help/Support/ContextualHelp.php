<?php

namespace App\Modules\Help\Support;

use Illuminate\Http\Request;

/**
 * Ajuda contextual (ADR 0006, parte 2): os artigos ligados à tela atual, pelo campo `route` do artigo, só os que a pessoa pode
 * ver (público, permissão e recurso do pacote). Vale a rota exata ou qualquer rota do mesmo recurso (`panel.games.uploads.*`
 * para um artigo cuja rota é `panel.games.uploads.index`); artigo de rota com um só nível (`panel.mesario`) vale só para ela.
 */
final class ContextualHelp
{
    private const MAX = 3;

    /**
     * @return array{area: string, articles: list<array{slug: string, title: string, url: string}>}|null
     */
    public static function for(Request $request): ?array
    {
        $user = $request->user();
        $name = $request->route()?->getName();

        if ($user === null || $name === null || ! str_contains($name, '.') || str_contains($name, '.help.')) {
            return null;
        }

        $area = explode('.', $name)[0];

        if (! in_array($area, HelpLibrary::AUDIENCES, true)) {
            return null;
        }

        $matches = array_values(array_filter(HelpLibrary::all(), fn (HelpArticle $article) => $article->route !== null && self::covers($article->route, $name)));
        $visible = HelpAccess::visible($matches, $user, $area);

        // A rota exata vem primeiro.
        usort($visible, fn (HelpArticle $a, HelpArticle $b) => [$a->route === $name ? 0 : 1, $a->order] <=> [$b->route === $name ? 0 : 1, $b->order]);

        if ($visible === []) {
            return null;
        }

        return [
            'area' => $area,
            'articles' => array_map(fn (HelpArticle $article) => ['slug' => $article->slug, 'title' => $article->title, 'url' => route("{$area}.help.show", $article->slug)], array_slice($visible, 0, self::MAX)),
        ];
    }

    public static function covers(string $articleRoute, string $current): bool
    {
        if ($articleRoute === $current) {
            return true;
        }

        $dot = strrpos($articleRoute, '.');

        if ($dot === false) {
            return false;
        }

        $prefix = substr($articleRoute, 0, $dot + 1);

        return substr_count($prefix, '.') >= 2 && str_starts_with($current, $prefix);
    }
}

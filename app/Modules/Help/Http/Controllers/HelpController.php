<?php

namespace App\Modules\Help\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Help\Support\HelpAccess;
use App\Modules\Help\Support\HelpArticle;
use App\Modules\Help\Support\HelpLibrary;
use App\Modules\Help\Support\HelpMarkdown;
use App\Modules\Help\Support\HelpSearch;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Central de ajuda (ADR 0006): busca e leitura dos artigos para operadores/administradores (`panel`), gestores de equipe
 * (`manager`) e a plataforma (`platform`). Mesma tela nos três; o que aparece depende do usuário, do perfil e do pacote.
 */
class HelpController extends Controller
{
    public function index(Request $request, string $area): Response
    {
        $visible = HelpAccess::visible(HelpLibrary::all(), $request->user(), $area);
        $query = trim((string) $request->query('q', ''));

        $results = $query === '' ? [] : array_map(fn (array $hit) => [...$this->summary($hit['article']), 'snippet' => $hit['snippet']], HelpSearch::search($visible, $query));

        $categories = [];

        foreach ($visible as $article) {
            $categories[$article->category][] = [...$this->summary($article), 'snippet' => $this->firstParagraph($article)];
        }

        return Inertia::render('Help/Index', [
            'area' => $area,
            'routes' => $this->routes($area),
            'q' => $query,
            'results' => $results,
            'categories' => collect($categories)->map(fn (array $articles, string $name) => ['name' => $name, 'articles' => $articles])->values(),
        ]);
    }

    public function show(Request $request, string $area, string $slug): Response
    {
        $visible = HelpAccess::visible(HelpLibrary::all(), $request->user(), $area);
        $article = collect($visible)->first(fn (HelpArticle $candidate) => $candidate->slug === $slug);
        abort_if($article === null, 404);

        $related = collect($visible)
            ->reject(fn (HelpArticle $other) => $other->slug === $article->slug)
            ->filter(fn (HelpArticle $other) => $other->category === $article->category || array_intersect($other->tags, $article->tags) !== [])
            ->take(4)
            ->map(fn (HelpArticle $other) => $this->summary($other))
            ->values();

        return Inertia::render('Help/Show', [
            'area' => $area,
            'routes' => $this->routes($area),
            'article' => [
                ...$this->summary($article),
                'tags' => $article->tags,
                'blocks' => HelpMarkdown::render($article->body, fn (string $target) => $this->link($target, $area, $visible)),
                'screen_url' => $article->route !== null ? $this->routeUrl($article->route) : null,
            ],
            'related' => $related,
        ]);
    }

    /**
     * @return array{slug: string, title: string, category: string}
     */
    private function summary(HelpArticle $article): array
    {
        return ['slug' => $article->slug, 'title' => $article->title, 'category' => $article->category];
    }

    private function firstParagraph(HelpArticle $article): string
    {
        foreach (preg_split('/\R\R/', $article->body) ?: [] as $block) {
            $text = HelpMarkdown::plain($block);

            if ($text !== '' && ! str_starts_with(trim($block), '#') && ! preg_match('/^(\d+\.|[-*])\s/', trim($block))) {
                return Str::limit($text, 140);
            }
        }

        return '';
    }

    /**
     * @return array{index: string, show: string}
     */
    private function routes(string $area): array
    {
        return ['index' => "{$area}.help.index", 'show' => "{$area}.help.show"];
    }

    /**
     * @param  list<HelpArticle>  $visible
     */
    private function link(string $target, string $area, array $visible): ?string
    {
        if (str_starts_with($target, 'help:')) {
            $slug = substr($target, 5);

            return collect($visible)->contains(fn (HelpArticle $article) => $article->slug === $slug) ? route("{$area}.help.show", $slug) : null;
        }

        if (str_starts_with($target, 'route:')) {
            return $this->routeUrl(substr($target, 6));
        }

        return null;
    }

    private function routeUrl(string $name): ?string
    {
        if (! Route::has($name)) {
            return null;
        }

        try {
            return route($name);
        } catch (UrlGenerationException) {
            return null;
        }
    }
}

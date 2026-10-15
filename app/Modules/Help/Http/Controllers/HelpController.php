<?php

namespace App\Modules\Help\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Help\Models\HelpFeedback;
use App\Modules\Help\Support\HelpAccess;
use App\Modules\Help\Support\HelpAiSettings;
use App\Modules\Help\Support\HelpAnswerer;
use App\Modules\Help\Support\HelpArticle;
use App\Modules\Help\Support\HelpLibrary;
use App\Modules\Help\Support\HelpMarkdown;
use App\Modules\Help\Support\HelpNotes;
use App\Modules\Help\Support\HelpSearch;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\DB;
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
    public function index(Request $request): Response
    {
        $area = $this->area($request);
        $visible = HelpAccess::visible($this->articles($area), $request->user(), $area);
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
            'ai' => in_array($area, ['panel', 'manager'], true) && HelpAiSettings::available() ? ['ask' => "{$area}.help.ask", 'remaining' => HelpAiSettings::remaining($request->user()), 'max_length' => HelpAnswerer::MAX_QUESTION] : null,
            ...($area === 'platform' ? ['feedback_summary' => $this->feedbackSummary()] : []),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $area = $this->area($request);
        $visible = HelpAccess::visible($this->articles($area), $request->user(), $area);
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
            'feedback' => HelpFeedback::query()->where('user_id', $request->user()->getAuthIdentifier())->where('slug', $article->slug)->value('helpful'),
        ]);
    }

    /**
     * Para a plataforma melhorar a documentação: os artigos com mais votos "não ajudou" (só quem ainda existe no acervo).
     *
     * @return list<array{slug: string, title: string, yes: int, no: int}>
     */
    private function feedbackSummary(): array
    {
        $rows = DB::table('help_feedback')
            ->selectRaw('slug, sum(case when helpful then 1 else 0 end) as yes_total, sum(case when helpful then 0 else 1 end) as no_total')
            ->groupBy('slug')
            ->get();

        return $rows
            ->map(fn ($row) => ['article' => HelpLibrary::find((string) $row->slug), 'yes' => (int) $row->yes_total, 'no' => (int) $row->no_total])
            ->filter(fn (array $item) => $item['article'] !== null && $item['no'] > 0)
            ->sortByDesc('no')
            ->take(10)
            ->map(fn (array $item) => ['slug' => $item['article']->slug, 'title' => $item['article']->title, 'yes' => $item['yes'], 'no' => $item['no']])
            ->values()
            ->all();
    }

    /**
     * Os artigos do acervo mais as notas da organização atual para a área (as notas só existem no painel e no gestor).
     *
     * @return list<HelpArticle>
     */
    private function articles(string $area): array
    {
        return [...HelpLibrary::all(), ...HelpNotes::articles($area)];
    }

    /** "Isso ajudou?": um voto por pessoa e artigo (o último vale), só para artigos que a pessoa pode ver. */
    public function feedback(Request $request, string $slug): RedirectResponse
    {
        $area = $this->area($request);
        $visible = HelpAccess::visible($this->articles($area), $request->user(), $area);
        abort_unless(collect($visible)->contains(fn (HelpArticle $candidate) => $candidate->slug === $slug), 404);

        $validated = $request->validate(['helpful' => ['required', 'boolean']]);

        HelpFeedback::query()->updateOrCreate(
            ['user_id' => $request->user()->getAuthIdentifier(), 'slug' => $slug],
            ['helpful' => (bool) $validated['helpful'], 'organization_id' => CurrentOrganization::id()],
        );

        return back()->with('success', 'Obrigado pelo retorno.');
    }

    /** A área (panel, manager ou platform) vem do padrão da rota; o Laravel passa os padrões depois dos parâmetros da URL. */
    private function area(Request $request): string
    {
        $area = (string) $request->route('area');
        abort_unless(in_array($area, ['panel', 'manager', 'athlete', 'platform'], true), 404);

        return $area;
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
     * @return array{index: string, show: string, feedback: string}
     */
    private function routes(string $area): array
    {
        return ['index' => "{$area}.help.index", 'show' => "{$area}.help.show", 'feedback' => "{$area}.help.feedback"];
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

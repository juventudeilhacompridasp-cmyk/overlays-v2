<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Push\PushConfig;
use App\Modules\Portal\Support\CompetitionPage;
use App\Modules\Portal\Support\GamePresenter;
use App\Modules\Portal\Support\IcsCalendar;
use App\Modules\Portal\Support\JsonLd;
use App\Modules\Portal\Support\PortalSettings;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Portal\Support\SeasonExtras;
use App\Modules\Portal\Support\SeasonPresenter;
use App\Modules\Portal\Support\SeasonStats;
use App\Modules\Portal\Support\TeamPage;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Portal público: tudo aqui é leitura, sem login, e só mostra edições publicadas (rascunho nunca aparece). */
class PortalController extends Controller
{
    private const SHOWN = 8;

    private const SEARCH_LIMIT = 15;

    public function home(): Response
    {
        $competitions = Competition::query()
            ->whereHas('seasons', fn ($query) => $query->published())
            ->with(['seasons' => fn ($query) => $query->published()->orderByDesc('year')->orderByDesc('name')])
            ->orderBy('name')->get();

        $withTeams = ['homeEntry.team', 'awayEntry.team', 'venue', 'group'];
        $published = fn ($query) => $query->whereHas('division.season', fn ($season) => $season->published());

        $live = Game::query()->where('status', GameStatus::InProgress)->tap($published)->with($withTeams)->orderBy('started_at')->limit(self::SHOWN)->get();
        $upcoming = Game::query()->where('status', GameStatus::Scheduled)->whereNotNull('scheduled_at')->where('scheduled_at', '>=', now()->startOfDay())
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')->tap($published)->with($withTeams)->orderBy('scheduled_at')->limit(self::SHOWN)->get();
        $recent = Game::query()->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])->tap($published)->with($withTeams)->orderByDesc('finished_at')->limit(self::SHOWN)->get();

        $theme = PublicTheme::current();

        return Inertia::render('Portal/Home', [
            'theme' => $theme,
            'seo' => $this->seo($theme['name'], $theme['tagline'] ?? "Competições, tabelas e resultados de {$theme['name']}.", route('portal.home')),
            'competitions' => $competitions->map(fn (Competition $competition) => [
                'id' => $competition->getKey(),
                'name' => $competition->name,
                'description' => $competition->description,
                'seasons' => $competition->seasons->map(fn (Season $season) => [
                    'id' => $season->getKey(),
                    'name' => $season->name,
                    'status_label' => $season->status->label(),
                    'url' => route('portal.season', [$competition->slug, $season->getKey()]),
                ])->values(),
            ])->values(),
            'live' => $live->map(fn (Game $game) => GamePresenter::game($game))->values(),
            'upcoming' => $upcoming->map(fn (Game $game) => GamePresenter::game($game))->values(),
            'recent' => $recent->map(fn (Game $game) => GamePresenter::game($game))->values(),
        ]);
    }

    /** `/competicoes/{slug}` leva à edição mais recente publicada. */
    public function competition(Competition $competition, CompetitionPage $page): RedirectResponse|Response
    {
        $latest = $competition->seasons()->published()->orderByDesc('year')->orderByDesc('name')->first();

        abort_if($latest === null, 404);

        if (! PortalSettings::enabled('competition_page')) {
            return redirect()->route('portal.season', [$competition->slug, $latest->getKey()]);
        }

        $theme = PublicTheme::current();

        return Inertia::render('Portal/Competition', [
            'theme' => $theme,
            'seo' => $this->seo("{$competition->name} · {$theme['name']}", "Histórico, campeões e recordes — {$competition->name}.", route('portal.competition', $competition->slug)),
            'competition' => ['name' => $competition->name, 'description' => $competition->description, 'sport' => $competition->sport->label()],
            'latest_url' => route('portal.season', [$competition->slug, $latest->getKey()]),
            ...$page->for($competition),
        ]);
    }

    public function season(Competition $competition, Season $season, SeasonPresenter $presenter, SeasonExtras $extras): Response
    {
        abort_unless($season->competition_id === $competition->getKey() && $season->isPublished(), 404);

        $data = $presenter->handle($season);
        $theme = PublicTheme::current();
        $title = "{$competition->name} {$season->name}";

        return Inertia::render('Portal/Season', [
            'theme' => $theme,
            'seo' => $this->seo("{$title} · {$theme['name']}", "Classificação, jogos, resultados e artilharia — {$title}.", route('portal.season', [$competition->slug, $season->getKey()]), null, JsonLd::season($competition, $season, Game::query()->whereIn('division_id', $season->divisions()->pluck('id'))->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage'])->orderByRaw('scheduled_at is null')->orderBy('scheduled_at')->limit(20)->get())),
            ...$data,
            'extras' => $extras->for($season),
            'stats_url' => route('portal.stats', [$competition->slug, $season->getKey()]),
            'registration_url' => CurrentOrganization::has(Feature::OnlineRegistration) && $season->registrationOpen() ? route('registration.apply', [$competition->slug, $season->getKey()]) : null,
            'other_seasons' => $competition->seasons()->published()->orderByDesc('year')->orderByDesc('name')->get()
                ->map(fn (Season $other) => ['id' => $other->getKey(), 'name' => $other->name, 'url' => route('portal.season', [$competition->slug, $other->getKey()]), 'current' => $other->is($season)])->values(),
        ]);
    }

    public function stats(Competition $competition, Season $season, SeasonStats $stats): Response
    {
        abort_unless($season->competition_id === $competition->getKey() && $season->isPublished(), 404);

        $theme = PublicTheme::current();
        $title = "{$competition->name} {$season->name}";

        return Inertia::render('Portal/Stats', [
            'theme' => $theme,
            'seo' => $this->seo("Estatísticas · {$title} · {$theme['name']}", "Ataque, defesa, forma recente e maiores resultados — {$title}.", route('portal.stats', [$competition->slug, $season->getKey()])),
            'competition' => ['name' => $competition->name, 'slug' => $competition->slug],
            'season' => ['id' => $season->getKey(), 'name' => $season->name, 'url' => route('portal.season', [$competition->slug, $season->getKey()])],
            ...$stats->handle($season),
        ]);
    }

    public function team(Team $team, TeamPage $page): Response
    {
        $entries = Entry::query()->where('team_id', $team->getKey())->where('status', EntryStatus::Confirmed)
            ->whereHas('division.season', fn ($season) => $season->published())
            ->with('division.season.competition')->get();

        abort_if($entries->isEmpty(), 404);

        $ids = $entries->pluck('id');
        $games = Game::query()
            ->where(fn (Builder $query) => $query->whereIn('home_entry_id', $ids)->orWhereIn('away_entry_id', $ids))
            ->whereIn('division_id', $entries->pluck('division_id'))
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'group', 'division.season.competition'])
            ->orderByRaw('scheduled_at is null')->orderByDesc('scheduled_at')->limit(40)->get();

        $theme = PublicTheme::current();

        return Inertia::render('Portal/Team', [
            'theme' => $theme,
            'seo' => $this->seo("{$team->name} · {$theme['name']}", "Jogos e resultados de {$team->name}.", route('portal.team', $team->getKey()), $team->logoUrl(512), JsonLd::teamPage($team)),
            'push_key' => PushConfig::publicKeyFor(),
            'team' => ['id' => $team->getKey(), 'name' => $team->name, 'city' => $team->city, 'logo_url' => $team->logoUrl(512), 'color' => $team->primary_color],
            'participations' => $entries->map(fn (Entry $entry) => [
                'competition' => $entry->division->season->competition->name,
                'season' => $entry->division->season->name,
                'division' => $entry->division->name,
                'url' => route('portal.season', [$entry->division->season->competition->slug, $entry->division->season->getKey()]),
            ])->values(),
            'games' => $games->map(fn (Game $game) => GamePresenter::game($game) + ['competition' => $game->division->season->competition->name])->values(),
            'extras' => $page->for($team, $entries),
        ]);
    }

    /** Calendário dos jogos da equipe (.ics), para assinar na agenda. Só edições publicadas; some se o interruptor estiver desligado. */
    public function teamCalendar(Team $team): HttpResponse
    {
        abort_unless(PortalSettings::enabled('team_calendar'), 404);

        $entries = Entry::query()->where('team_id', $team->getKey())->where('status', EntryStatus::Confirmed)
            ->whereHas('division.season', fn ($season) => $season->published())->get();

        abort_if($entries->isEmpty(), 404);

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'portal';

        $games = Game::query()
            ->where(fn (Builder $query) => $query->whereIn('home_entry_id', $entries->pluck('id'))->orWhereIn('away_entry_id', $entries->pluck('id')))
            ->whereIn('division_id', $entries->pluck('division_id'))
            ->whereNotNull('scheduled_at')->whereNotIn('status', [GameStatus::Cancelled])
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition'])
            ->orderBy('scheduled_at')->limit(300)->get();

        $events = $games->map(fn (Game $game) => [
            'uid' => $game->getKey().'@'.$host,
            'start' => $game->scheduled_at,
            'minutes' => 120,
            'summary' => ($game->homeEntry?->team->name ?? 'A definir').' x '.($game->awayEntry?->team->name ?? 'A definir'),
            'location' => $game->venue?->name,
            'url' => route('public.games.show', $game->getKey()),
            'description' => $game->division->season->competition->name.' · '.$game->division->name,
        ])->values()->all();

        return response(IcsCalendar::render("{$team->name} — jogos", $events), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.Str::slug($team->name).'.ics"',
        ]);
    }

    public function sitemap(): HttpResponse
    {
        $urls = [route('portal.home')];

        foreach (Competition::query()->with(['seasons' => fn ($query) => $query->published()])->get() as $competition) {
            foreach ($competition->seasons as $season) {
                $urls[] = route('portal.season', [$competition->slug, $season->getKey()]);
                $urls[] = route('portal.stats', [$competition->slug, $season->getKey()]);
            }
        }

        $teams = Team::query()->whereHas('entries', fn (Builder $entry) => $entry->where('status', EntryStatus::Confirmed)->whereHas('division.season', fn ($season) => $season->published()))->pluck('id');

        foreach ($teams as $id) {
            $urls[] = route('portal.team', $id);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Busca do portal: competições e equipes de edições publicadas pelo nome (nunca atletas). Página sem indexação. */
    public function search(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));
        $term = mb_substr($term, 0, 60);
        $theme = PublicTheme::current();
        $competitions = collect();
        $teams = collect();

        if (mb_strlen($term) >= 2) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

            $competitions = Competition::query()->whereHas('seasons', fn ($query) => $query->published())
                ->where('name', 'ilike', $like)->orderBy('name')->limit(self::SEARCH_LIMIT)->get();

            $teams = Team::query()
                ->whereHas('entries', fn ($query) => $query->where('status', EntryStatus::Confirmed)->whereHas('division.season', fn ($season) => $season->published()))
                ->where(fn (Builder $query) => $query->where('name', 'ilike', $like)->orWhere('city', 'ilike', $like))
                ->orderBy('name')->limit(self::SEARCH_LIMIT)->get();
        }

        return Inertia::render('Portal/Search', [
            'theme' => $theme,
            'seo' => $this->seo("Busca · {$theme['name']}", "Busque competições e equipes de {$theme['name']}.", route('portal.search'), null, null, true),
            'query' => $term,
            'competitions' => $competitions->map(fn (Competition $competition) => ['name' => $competition->name, 'url' => route('portal.competition', $competition->slug)])->values(),
            'teams' => $teams->map(fn (Team $team) => ['name' => $team->name, 'city' => $team->city, 'logo_url' => $team->logoUrl(128), 'url' => route('portal.team', $team->getKey())])->values(),
        ]);
    }

    public function robots(): HttpResponse
    {
        $body = "User-agent: *\nDisallow: /painel\nDisallow: /overlay\nDisallow: /minha-inscricao\nDisallow: /carteirinha\nDisallow: /busca\nDisallow: /api\nDisallow: /login\nDisallow: /register\n\nSitemap: ".route('portal.sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>|null  $jsonLd  dados estruturados (schema.org) da página
     * @return array{title: string, description: string, url: string, image: string|null, json_ld?: array<string, mixed>, noindex?: bool}
     */
    private function seo(string $title, string $description, string $url, ?string $image = null, ?array $jsonLd = null, bool $noindex = false): array
    {
        return ['title' => $title, 'description' => $description, 'url' => $url, 'image' => $image]
            + ($jsonLd === null ? [] : ['json_ld' => $jsonLd])
            + ($noindex ? ['noindex' => true] : []);
    }
}

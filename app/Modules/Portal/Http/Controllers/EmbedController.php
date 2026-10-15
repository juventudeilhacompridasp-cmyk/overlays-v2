<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Portal\Support\GamePresenter;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Portal\Support\SeasonPresenter;
use App\Modules\Tenancy\Support\PortalQuantities;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Widgets para colar em outros sites (iframe): placar de um jogo, classificação de uma divisão e jogos de uma edição.
 * Mesmo recorte do portal público: só edições publicadas, sem cartões, disciplina ou dados pessoais.
 * Aparência por parâmetros: `?tema=escuro` e `?cor=%23059669` (cor de destaque).
 */
class EmbedController extends Controller
{
    public function game(Request $request, Game $game): Response
    {
        abort_unless($game->division->season->isPublished(), 404);

        $realtime = config('platform.realtime');

        return Inertia::render('Embed/Game', [
            'options' => $this->options($request),
            'snapshot' => PublicGameSnapshot::for($game),
            'realtime' => ['key' => $realtime['key'], 'host' => $realtime['host'], 'port' => $realtime['port'], 'scheme' => $realtime['scheme']],
            'portal_url' => route('public.games.show', $game->getKey()),
        ]);
    }

    public function standings(Request $request, Division $division, SeasonPresenter $presenter): Response
    {
        $season = $division->season;
        abort_unless($season->isPublished(), 404);

        $data = $presenter->handle($season);
        $divisionData = collect($data['divisions'])->firstWhere('id', $division->getKey());
        $stage = collect($divisionData['stages'] ?? [])->first(fn (array $stage) => ! empty($stage['tables']));
        $limit = max(1, min(50, $request->integer('linhas', 10)));

        return Inertia::render('Embed/Standings', [
            'options' => $this->options($request),
            'title' => "{$data['competition']['name']} · {$division->name}",
            'tables' => collect($stage['tables'] ?? [])->map(fn (array $table) => ['name' => $table['name'], 'rows' => array_slice($table['rows'], 0, $limit)])->values()->all(),
            'portal_url' => route('portal.season', [$data['competition']['slug'], $season->getKey()]),
        ]);
    }

    public function games(Request $request, Season $season): Response
    {
        abort_unless($season->isPublished(), 404);

        $season->loadMissing('competition');
        $divisions = $season->divisions()->pluck('id');
        $query = fn () => Game::query()->whereIn('division_id', $divisions)->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')->with(['homeEntry.team', 'awayEntry.team', 'venue', 'group']);
        $present = fn ($games) => $games->map(fn (Game $game) => GamePresenter::game($game))->values()->all();
        $shown = PortalQuantities::all()['widgets'];

        return Inertia::render('Embed/Games', [
            'options' => $this->options($request),
            'title' => "{$season->competition->name} {$season->name}",
            'live' => $present($query()->where('status', GameStatus::InProgress)->orderBy('started_at')->get()),
            'upcoming' => $present($query()->where('status', GameStatus::Scheduled)->whereNotNull('scheduled_at')->where('scheduled_at', '>=', now()->subHours(3))->orderBy('scheduled_at')->limit($shown)->get()),
            'recent' => $present($query()->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])->orderByDesc('finished_at')->orderByDesc('id')->limit($shown)->get()),
            'portal_url' => route('portal.season', [$season->competition->slug, $season->getKey()]),
        ]);
    }

    /** @return array{dark: bool, accent: string, name: string} */
    private function options(Request $request): array
    {
        $accent = $request->query('cor');

        return [
            'dark' => $request->query('tema') === 'escuro',
            'accent' => is_string($accent) && preg_match('/^#[0-9a-fA-F]{6}$/', $accent) === 1 ? $accent : PublicTheme::current()['primary'],
            'name' => PublicTheme::current()['name'],
        ];
    }
}

<?php

namespace App\Modules\Portal\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Portal\Support\SeasonPresenter;
use Illuminate\Http\JsonResponse;

/**
 * API pública v1 (somente leitura, sem autenticação). Mesmo recorte de dados do portal: edições publicadas,
 * sem cartões, disciplina, escalação ou dados pessoais. Mudanças que quebram o formato vão para /v2.
 */
class V1Controller extends Controller
{
    public function competitions(): JsonResponse
    {
        $competitions = Competition::query()
            ->whereHas('seasons', fn ($query) => $query->published())
            ->with(['seasons' => fn ($query) => $query->published()->orderByDesc('year')->orderByDesc('name')])
            ->orderBy('name')->get();

        return response()->json(['data' => $competitions->map(fn (Competition $competition) => [
            'id' => $competition->getKey(),
            'name' => $competition->name,
            'slug' => $competition->slug,
            'sport' => $competition->sport->value,
            'seasons' => $competition->seasons->map(fn (Season $season) => [
                'id' => $season->getKey(),
                'name' => $season->name,
                'year' => $season->year,
                'status' => $season->status->value,
                'url' => route('api.v1.seasons.show', $season->getKey()),
            ])->values(),
        ])->values()]);
    }

    public function season(Season $season, SeasonPresenter $presenter): JsonResponse
    {
        abort_unless($season->isPublished(), 404);

        return response()->json(['data' => $presenter->handle($season)]);
    }

    public function game(Game $game): JsonResponse
    {
        abort_unless($game->division->season->isPublished(), 404);

        return response()->json(['data' => PublicGameSnapshot::for($game)]);
    }
}

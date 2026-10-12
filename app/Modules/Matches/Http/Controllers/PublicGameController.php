<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Placar ao vivo público: snapshot inicial + deltas pelo canal `games.{id}` do Reverb. */
class PublicGameController extends Controller
{
    public function show(Game $game): Response
    {
        abort_unless($game->division->season->isPublished(), 404);

        $realtime = config('platform.realtime');

        return Inertia::render('Public/GameLive', [
            'snapshot' => PublicGameSnapshot::for($game),
            'realtime' => [
                'key' => $realtime['key'],
                'host' => $realtime['host'],
                'port' => $realtime['port'],
                'scheme' => $realtime['scheme'],
            ],
        ]);
    }

    /** Usado como contingência quando o WebSocket não conecta. */
    public function state(Game $game): JsonResponse
    {
        abort_unless($game->division->season->isPublished(), 404);

        return response()->json(PublicGameSnapshot::for($game));
    }
}

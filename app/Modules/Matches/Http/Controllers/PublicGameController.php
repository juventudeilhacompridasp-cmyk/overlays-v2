<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Portal\Push\PushConfig;
use App\Modules\Portal\Support\GameInsights;
use App\Modules\Portal\Support\GameShareImage;
use App\Modules\Portal\Support\JsonLd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Placar ao vivo público: snapshot inicial + deltas pelo canal `games.{id}` do Reverb. */
class PublicGameController extends Controller
{
    public function show(Game $game, GameInsights $insights): Response
    {
        abort_unless($game->division->season->isPublished(), 404);

        $realtime = config('platform.realtime');

        return Inertia::render('Public/GameLive', [
            'snapshot' => PublicGameSnapshot::for($game),
            // Reenviado sozinho (recarga parcial) quando o placar ou a situação mudam, para a linha do tempo acompanhar o jogo.
            'insights' => fn () => $insights->for($game),
            'json_ld' => JsonLd::game($game),
            'share_image' => route('public.games.image', $game->getKey()),
            'push_key' => PushConfig::publicKeyFor(),
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

    /** Imagem 1200 x 630 para pré-visualização em redes (Open Graph); só dados públicos. */
    public function image(Game $game): HttpResponse
    {
        abort_unless($game->division->season->isPublished(), 404);

        return response(GameShareImage::png($game), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}

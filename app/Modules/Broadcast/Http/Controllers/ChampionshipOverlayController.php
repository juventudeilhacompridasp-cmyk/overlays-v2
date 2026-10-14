<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\ChampionshipBundle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Overlays de campeonato (tabela, jogos, artilharia, cartões, jogo em destaque, faixa e campeão). A aparência vem da URL
 * (`?style=&anim=...`); os dados, da edição publicada pedida em `?edicao=`/`?c=` ou do jogo no ar do canal. Sem login: o
 * token do canal é o acesso, e a página só lê dados que o portal público já mostra.
 */
class ChampionshipOverlayController extends Controller
{
    public const VIEWS = ['standings', 'fixtures', 'results', 'scorers', 'discipline', 'matchcard', 'ticker', 'champion'];

    public function show(OverlayChannel $channel, string $view): Response
    {
        abort_unless(in_array($view, self::VIEWS, true), 404);

        $realtime = config('platform.realtime');

        return Inertia::render('Overlay/Championship', [
            'token' => $channel->token,
            'view' => $view,
            'realtime' => ['key' => $realtime['key'], 'host' => $realtime['host'], 'port' => $realtime['port'], 'scheme' => $realtime['scheme']],
        ]);
    }

    /** Dados do campeonato; o OBS consulta de tempos em tempos (`refresh`). `null` = nada a mostrar ainda. */
    public function data(Request $request, OverlayChannel $channel): JsonResponse
    {
        $channel->loadMissing('game.division.season.competition');
        $season = ChampionshipBundle::resolve($request, $channel->game);
        $division = $request->query('div');
        $bundle = $season === null ? null : ChampionshipBundle::for($season, is_string($division) ? $division : null);

        return response()->json(['data' => $bundle])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }
}

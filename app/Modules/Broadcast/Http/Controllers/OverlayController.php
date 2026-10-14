<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayState;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Telas que o OBS carrega como fonte de navegador. Sem login: o token da URL é o acesso. */
class OverlayController extends Controller
{
    private const PAGES = [
        'placar' => 'Overlay/Scoreboard',
        'eventos' => 'Overlay/LowerThird',
        'escalacao' => 'Overlay/Lineup',
        'tabela' => 'Overlay/Standings',
        'patrocinadores' => 'Overlay/Sponsors',
    ];

    public function show(OverlayChannel $channel, string $overlay): Response
    {
        abort_unless(isset(self::PAGES[$overlay]), 404);

        $realtime = config('platform.realtime');

        return Inertia::render(self::PAGES[$overlay], [
            'token' => $channel->token,
            'state' => OverlayState::for($channel),
            'realtime' => ['key' => $realtime['key'], 'host' => $realtime['host'], 'port' => $realtime['port'], 'scheme' => $realtime['scheme']],
        ]);
    }

    /** Contingência quando o WebSocket não conecta (o OBS mantém a página aberta o dia todo). */
    public function state(OverlayChannel $channel): JsonResponse
    {
        return response()->json(OverlayState::for($channel))->header('Cache-Control', 'no-store');
    }
}

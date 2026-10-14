<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\SaveOverlayFont;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayFont;
use App\Modules\Broadcast\Support\OverlayState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as PlainResponse;
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
        'prejogo' => 'Overlay/Pregame',
        'comparativo' => 'Overlay/Compare',
        'cartela' => 'Overlay/Cartela',
        'resumo' => 'Overlay/Summary',
        'chaveamento' => 'Overlay/Bracket',
        'social' => 'Overlay/Social',
        'barra-patrocinio' => 'Overlay/SponsorBar',
        'estatisticas' => 'Overlay/Stats',
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

    /** Arquivo da fonte própria que o canal usa (mesma origem do overlay; o token do canal é o acesso). */
    public function font(OverlayChannel $channel, OverlayFont $font): PlainResponse
    {
        $contents = SaveOverlayFont::contents($font);

        abort_if($contents === null, 404);

        return response($contents, 200, ['Content-Type' => $font->contentType(), 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Contingência quando o WebSocket não conecta (o OBS mantém a página aberta o dia todo). */
    public function state(OverlayChannel $channel): JsonResponse
    {
        return response()->json(OverlayState::for($channel))->header('Cache-Control', 'no-store');
    }
}

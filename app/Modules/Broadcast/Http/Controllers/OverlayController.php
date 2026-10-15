<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\SaveOverlayFont;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayCustomItem;
use App\Modules\Broadcast\Models\OverlayFont;
use App\Modules\Broadcast\Support\OverlayCustomElements;
use App\Modules\Broadcast\Support\OverlayState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        'penaltis' => 'Overlay/Shootout',
        'todos' => 'Overlay/All',
        // Monitor do Modo operador (não é fonte do OBS; fora de `OverlayChannel::OVERLAYS`).
        'pgm' => 'Overlay/Pgm',
    ];

    public function show(Request $request, OverlayChannel $channel, string $overlay): Response
    {
        abort_unless(isset(self::PAGES[$overlay]), 404);

        $realtime = config('platform.realtime');
        $preview = $overlay === 'pgm' && $request->has('pvw');

        return Inertia::render(self::PAGES[$overlay], [
            'token' => $channel->token,
            'state' => OverlayState::for($preview ? $this->previewed($request, $channel) : $channel),
            'preview' => $preview,
            'realtime' => ['key' => $realtime['key'], 'host' => $realtime['host'], 'port' => $realtime['port'], 'scheme' => $realtime['scheme']],
        ]);
    }

    /** Overlay livre (Builder): o desenho vai saneado e com as imagens de patrocinador já resolvidas; ligar e desligar é pelo canal. */
    public function custom(OverlayChannel $channel, OverlayCustomItem $item): Response
    {
        $realtime = config('platform.realtime');

        return Inertia::render('Overlay/Custom', [
            'token' => $channel->token,
            'state' => OverlayState::for($channel),
            'item' => ['id' => $item->getKey(), 'name' => $item->name, 'width' => $item->width, 'height' => $item->height, 'elements' => OverlayCustomElements::resolve($item->elements)],
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
    public function state(Request $request, OverlayChannel $channel): JsonResponse
    {
        return response()->json(OverlayState::for($this->previewed($request, $channel)))->header('Cache-Control', 'no-store');
    }

    /**
     * PVW do Modo operador: `?pvw=lineup` monta o estado com aquela camada/tela ligada SEM gravar nada. Só vale com sessão do
     * painel (o token da URL sozinho nunca mostra o que ainda não está no ar); sem `pvw` o canal segue como está.
     */
    private function previewed(Request $request, OverlayChannel $channel): OverlayChannel
    {
        if (! $request->has('pvw')) {
            return $channel;
        }

        abort_unless($request->user()?->role()?->usesPanel() === true, 403);

        return $channel->withPreview((string) $request->query('pvw'));
    }
}

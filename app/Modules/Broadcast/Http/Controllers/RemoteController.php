<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayCustomItem;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Support\ChannelPayload;
use App\Modules\Matches\Models\Game;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controle remoto da transmissão para celular e tablet: uma tela só para tocar (botões grandes), com os mesmos dados e a
 * MESMA rota de comandos (`panel.broadcast.update`) do Modo operador. Qualquer membro abre; só quem opera jogos comanda.
 */
class RemoteController extends Controller
{
    public function show(): Response
    {
        $channels = OverlayChannel::query()->with(['game.homeEntry.team', 'game.awayEntry.team', 'game.lineups.person'])->orderBy('name')->get();
        $customItems = OverlayCustomItem::query()->orderBy('name')->get();

        return Inertia::render('Panel/Broadcast/Remote', [
            'channels' => $channels->map(fn (OverlayChannel $channel): array => ChannelPayload::for($channel, $customItems, ChannelPayload::gameLabel(...)))->values(),
            'games' => ChannelPayload::candidateGames()->map(fn (Game $game) => ['id' => $game->id, 'label' => ChannelPayload::gameLabel($game)])->values(),
            'scenes' => OverlayScene::query()->orderBy('name')->get()->map(fn (OverlayScene $scene) => ['id' => $scene->id, 'name' => $scene->name])->values(),
        ]);
    }
}

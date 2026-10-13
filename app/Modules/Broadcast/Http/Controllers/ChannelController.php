<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\UpdateOverlayChannel;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayControlRules;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Painel "Transmissão": canais do OBS, jogo no ar e camadas sob demanda. */
class ChannelController extends Controller
{
    public function index(): Response
    {
        $channels = OverlayChannel::query()->with('game.homeEntry.team', 'game.awayEntry.team')->orderBy('name')->get();

        $label = fn (Game $game) => ($game->homeEntry?->team->name ?? 'A definir').' x '.($game->awayEntry?->team->name ?? 'A definir')
            .($game->scheduled_at ? ' · '.$game->scheduled_at->format('d/m H:i') : '');

        $candidates = Game::query()
            ->with('homeEntry.team', 'awayEntry.team')
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->whereIn('status', [GameStatus::Scheduled, GameStatus::InProgress])
            ->orderByRaw('scheduled_at is null')->orderBy('scheduled_at')->orderBy('number')
            ->limit(60)->get();

        return Inertia::render('Panel/Broadcast/Index', [
            'channels' => $channels->map(fn (OverlayChannel $channel) => [
                'id' => $channel->id,
                'name' => $channel->name,
                'game_id' => $channel->game_id,
                'game_label' => $channel->game ? $label($channel->game) : null,
                'layers' => array_merge(array_fill_keys(OverlayChannel::LAYERS, false), array_map('boolval', $channel->layers ?? [])),
                'appearance' => OverlayAppearance::normalize($channel->appearance),
                'has_control_token' => $channel->control_token_hash !== null,
                'control_url' => route('api.v1.overlay-channels.state.update', ['channel' => $channel]),
                'overlays' => collect(OverlayChannel::OVERLAYS)->map(fn (string $title, string $slug) => [
                    'slug' => $slug, 'title' => $title, 'url' => $channel->overlayUrl($slug),
                ])->values(),
            ])->values(),
            'games' => $candidates->map(fn (Game $game) => ['id' => $game->id, 'label' => $label($game)])->values(),
            'issued_control' => session()->pull('overlay_issued_control'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('overlay_channels', 'name')->where('organization_id', CurrentOrganization::id())],
        ], [], ['name' => 'nome']);

        $controlToken = OverlayChannel::newControlToken();
        $channel = OverlayChannel::query()->create([
            'name' => $validated['name'],
            'token' => OverlayChannel::newToken(),
            'control_token_hash' => OverlayChannel::controlTokenHash($controlToken),
            'layers' => [],
            'appearance' => OverlayAppearance::defaults(),
        ]);

        return back()
            ->with('success', 'Canal criado. Copie as URLs para o OBS e guarde a chave da API.')
            ->with('overlay_issued_control', ['channel_id' => $channel->id, 'token' => $controlToken]);
    }

    /** Operação ao vivo: escolhe o jogo no ar e liga/desliga camadas. */
    public function update(Request $request, OverlayChannel $channel, UpdateOverlayChannel $update): RedirectResponse
    {
        $update->handle($channel, $request->validate(OverlayControlRules::for($channel)));

        return back()->with('success', 'Transmissão atualizada.');
    }

    /** Invalida as URLs antigas (ex.: se o link vazou). As fontes do OBS precisam ser atualizadas. */
    public function regenerate(OverlayChannel $channel): RedirectResponse
    {
        $channel->update(['token' => OverlayChannel::newToken()]);

        return back()->with('success', 'Token renovado. Atualize as URLs no OBS.');
    }

    /** Emite uma nova chave de escrita; o valor em claro aparece somente nesta resposta. */
    public function regenerateControlToken(OverlayChannel $channel): RedirectResponse
    {
        $controlToken = OverlayChannel::newControlToken();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($controlToken)]);

        return back()
            ->with('success', 'Chave da API renovada. A chave anterior deixou de funcionar.')
            ->with('overlay_issued_control', ['channel_id' => $channel->id, 'token' => $controlToken]);
    }

    public function destroy(OverlayChannel $channel): RedirectResponse
    {
        $channel->delete();

        return back()->with('success', 'Canal excluído.');
    }
}

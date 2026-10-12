<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
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
                'overlays' => collect(OverlayChannel::OVERLAYS)->map(fn (string $title, string $slug) => [
                    'slug' => $slug, 'title' => $title, 'url' => $channel->overlayUrl($slug),
                ])->values(),
            ])->values(),
            'games' => $candidates->map(fn (Game $game) => ['id' => $game->id, 'label' => $label($game)])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('overlay_channels', 'name')->where('organization_id', CurrentOrganization::id())],
        ], [], ['name' => 'nome']);

        OverlayChannel::query()->create(['name' => $validated['name'], 'token' => OverlayChannel::newToken(), 'layers' => []]);

        return back()->with('success', 'Canal criado. Copie as URLs para as fontes de navegador do OBS.');
    }

    /** Operação ao vivo: escolhe o jogo no ar e liga/desliga camadas. */
    public function update(Request $request, OverlayChannel $channel): RedirectResponse
    {
        $validated = $request->validate([
            'game_id' => ['sometimes', 'nullable', 'string', Rule::exists('games', 'id')->where('organization_id', $channel->organization_id)],
            'layers' => ['sometimes', 'array'],
            'layers.*' => ['boolean'],
        ]);

        if (array_key_exists('game_id', $validated)) {
            $channel->game_id = $validated['game_id'];
        }

        if (isset($validated['layers'])) {
            $channel->layers = array_merge(
                $channel->layers ?? [],
                array_intersect_key(array_map('boolval', $validated['layers']), array_flip(OverlayChannel::LAYERS)),
            );
        }

        $channel->save();
        event(OverlayUpdated::for($channel));

        return back()->with('success', 'Transmissão atualizada.');
    }

    /** Invalida as URLs antigas (ex.: se o link vazou). As fontes do OBS precisam ser atualizadas. */
    public function regenerate(OverlayChannel $channel): RedirectResponse
    {
        $channel->update(['token' => OverlayChannel::newToken()]);

        return back()->with('success', 'Token renovado. Atualize as URLs no OBS.');
    }

    public function destroy(OverlayChannel $channel): RedirectResponse
    {
        $channel->delete();

        return back()->with('success', 'Canal excluído.');
    }
}

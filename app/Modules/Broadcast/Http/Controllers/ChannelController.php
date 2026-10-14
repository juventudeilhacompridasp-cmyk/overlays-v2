<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\UpdateOverlayChannel;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayFont;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Models\OverlaySponsor;
use App\Modules\Broadcast\Models\OverlayStyleModel;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayControlRules;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlayLineupTimeline;
use App\Modules\Broadcast\Support\OverlayLowerThirdSettings;
use App\Modules\Broadcast\Support\OverlaySponsorSettings;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
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
        $channels = OverlayChannel::query()->with([
            'game.homeEntry.team',
            'game.awayEntry.team',
            'game.lineups.person',
        ])->orderBy('name')->get();

        $label = fn (Game $game) => ($game->homeEntry?->team->name ?? 'A definir').' x '.($game->awayEntry?->team->name ?? 'A definir')
            .($game->scheduled_at ? ' · '.$game->scheduled_at->format('d/m H:i') : '');

        $candidates = Game::query()
            ->with('homeEntry.team', 'awayEntry.team')
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->whereIn('status', [GameStatus::Scheduled, GameStatus::InProgress])
            ->orderByRaw('scheduled_at is null')->orderBy('scheduled_at')->orderBy('number')
            ->limit(60)->get();

        return Inertia::render('Panel/Broadcast/Index', [
            'channels' => $channels->map(function (OverlayChannel $channel) use ($label): array {
                $game = $channel->game;
                $people = $game?->lineups
                    ->whereIn('slot', [LineupSlot::Starter, LineupSlot::Bench])
                    ->map(function (GameLineup $lineup) use ($game): array {
                        $person = $lineup->person;
                        $team = $lineup->entry_id === $game?->home_entry_id ? $game->homeEntry?->team : $game?->awayEntry?->team;

                        return [
                            'id' => $person->id,
                            'name' => $person->nickname ?: $person->name,
                            'shirt' => $lineup->shirt_number,
                            'team' => $team->name,
                            'has_photo' => $person->photo_path !== null,
                            'photo_url' => $person->photoUrl(96),
                        ];
                    })
                    ->sortBy(fn (array $person) => ($person['team'].'|'.str_pad((string) ($person['shirt'] ?? 999), 3, '0', STR_PAD_LEFT)))
                    ->values() ?? collect();

                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'game_id' => $channel->game_id,
                    'game_label' => $game ? $label($game) : null,
                    'layers' => $channel->layerStates(),
                    'championship_live' => $channel->championshipLive(),
                    'appearance' => OverlayAppearance::normalize($channel->appearance),
                    'lineup_settings' => OverlayLineupSettings::normalize($channel->lineup_settings),
                    'sponsor_settings' => OverlaySponsorSettings::normalize($channel->sponsor_settings),
                    'lower_third_settings' => OverlayLowerThirdSettings::normalize($channel->lower_third_settings),
                    'lineup_people' => $people,
                    'lineup_steps' => OverlayLineupTimeline::steps(OverlayLineupSettings::normalize($channel->lineup_settings)['presentation'], OverlayState::individualCount($channel)),
                    'championship_base' => substr(route('overlay.championship', ['channel' => $channel->token, 'view' => 'standings']), 0, -strlen('/standings')),
                    'has_control_token' => $channel->control_token_hash !== null,
                    'control_url' => route('api.v1.overlay-channels.state.update', ['channel' => $channel]),
                    'overlays' => collect(OverlayChannel::OVERLAYS)->map(fn (string $title, string $slug) => [
                        'slug' => $slug, 'title' => $title, 'url' => $channel->overlayUrl($slug),
                    ])->values(),
                ];
            })->values(),
            'sponsors' => OverlaySponsor::query()->orderBy('sort')->orderBy('created_at')->get()->map(fn (OverlaySponsor $sponsor) => [
                'id' => $sponsor->id, 'name' => $sponsor->name, 'active' => $sponsor->active, 'logo_url' => $sponsor->logoUrl(128),
            ])->values(),
            'sponsor_limit' => OverlaySponsor::LIMIT,
            'fonts' => OverlayFont::query()->orderBy('name')->get()->map(fn (OverlayFont $font) => ['id' => $font->id, 'name' => $font->name, 'format' => $font->format])->values(),
            'font_limit' => OverlayFont::LIMIT,
            'scenes' => OverlayScene::query()->orderBy('name')->get()->map(fn (OverlayScene $scene) => ['id' => $scene->id, 'name' => $scene->name])->values(),
            'scene_limit' => OverlayScene::LIMIT,
            'style_models' => OverlayStyleModel::query()->orderBy('name')->get()->map(fn (OverlayStyleModel $model) => ['id' => $model->id, 'name' => $model->name, 'options' => $model->options])->values(),
            'style_model_limit' => OverlayStyleModel::LIMIT,
            'championship_seasons' => Season::query()->published()->with(['competition', 'divisions' => fn ($query) => $query->orderBy('name')])
                ->orderByDesc('year')->orderByDesc('created_at')->limit(40)->get()
                ->map(fn (Season $season) => [
                    'id' => $season->getKey(),
                    'label' => $season->competition->name.' · '.$season->name,
                    'divisions' => $season->divisions->map(fn ($division) => ['id' => $division->getKey(), 'name' => $division->name])->values(),
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
            'lineup_settings' => OverlayLineupSettings::defaults(),
            'sponsor_settings' => OverlaySponsorSettings::defaults(),
        ]);

        return back()
            ->with('success', 'Canal criado. Copie as URLs para o OBS e guarde a chave da API.')
            ->with('overlay_issued_control', ['channel_id' => $channel->id, 'token' => $controlToken]);
    }

    /** Operação ao vivo: escolhe o jogo no ar e liga/desliga camadas. */
    public function update(Request $request, OverlayChannel $channel, UpdateOverlayChannel $update): RedirectResponse
    {
        $gameId = $request->exists('game_id')
            ? (is_string($request->input('game_id')) ? $request->input('game_id') : null)
            : $channel->game_id;

        $update->handle($channel, $request->validate(OverlayControlRules::for($channel, $gameId)));

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

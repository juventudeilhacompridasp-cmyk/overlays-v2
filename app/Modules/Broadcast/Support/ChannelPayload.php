<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayCustomItem;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use Closure;
use Illuminate\Support\Collection;

/**
 * Dados de um canal para as telas do painel (quadro, Modo operador e Controle remoto): estado das camadas, telas extras,
 * escalação e URLs. É a MESMA forma para todas as telas, para o que o operador vê no celular bater com o que vê no
 * computador. Nunca leva dado pessoal além do apelido/nome e da foto assinada de quem está na escalação do jogo no ar.
 */
final class ChannelPayload
{
    /** Rótulo do jogo nas listas: "A x B · 25/10 16:00". */
    public static function gameLabel(Game $game): string
    {
        return ($game->homeEntry?->team->name ?? 'A definir').' x '.($game->awayEntry?->team->name ?? 'A definir')
            .($game->scheduled_at ? ' · '.$game->scheduled_at->format('d/m H:i') : '');
    }

    /**
     * Jogos que podem ir ao ar: agendados ou em andamento, com as duas equipes definidas.
     *
     * @return Collection<int, Game>
     */
    public static function candidateGames(): Collection
    {
        return Game::query()
            ->with('homeEntry.team', 'awayEntry.team')
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->whereIn('status', [GameStatus::Scheduled, GameStatus::InProgress])
            ->orderByRaw('scheduled_at is null')->orderBy('scheduled_at')->orderBy('number')
            ->limit(60)->get();
    }

    /**
     * @param  Collection<int, OverlayCustomItem>  $customItems
     * @param  Closure(Game): string  $label  rótulo do jogo ("A x B · 25/10 16:00")
     * @return array<string, mixed>
     */
    public static function for(OverlayChannel $channel, Collection $customItems, Closure $label): array
    {
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

        $plan = OverlayState::lineupPlan($channel);
        $screenSettings = OverlayScreenSettings::normalize($channel->screen_settings);

        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'game_id' => $channel->game_id,
            'game_label' => $game ? $label($game) : null,
            'layers' => $channel->layerStates(),
            'championship_live' => $channel->championshipLive(),
            'stoppage' => $screenSettings['stoppage'],
            'screens_live' => $channel->screensLive(),
            'custom_overlays' => $customItems->map(fn (OverlayCustomItem $item) => ['id' => $item->getKey(), 'name' => $item->name, 'url' => $channel->customUrl((string) $item->getKey()), 'live' => ($channel->customLive()[$item->getKey()] ?? false) === true])->values(),
            'screen_settings' => $screenSettings,
            'stats_settings' => OverlayStatsSettings::normalize($channel->stats_settings),
            'appearance' => OverlayAppearance::normalize($channel->appearance),
            'lineup_settings' => OverlayLineupSettings::normalize($channel->lineup_settings),
            'sponsor_settings' => OverlaySponsorSettings::normalize($channel->sponsor_settings),
            'lower_third_settings' => OverlayLowerThirdSettings::normalize($channel->lower_third_settings),
            'lineup_people' => $people,
            'lineup_steps' => OverlayLineupTimeline::steps(OverlayLineupSettings::normalize($channel->lineup_settings), $plan['individuals'], $plan['formation']),
            'championship_base' => substr(route('overlay.championship', ['channel' => $channel->token, 'view' => 'standings']), 0, -strlen('/standings')),
            'pgm_url' => $channel->overlayUrl('pgm'),
            'has_control_token' => $channel->control_token_hash !== null,
            'control_url' => route('api.v1.overlay-channels.state.update', ['channel' => $channel]),
            'overlays' => collect(OverlayChannel::OVERLAYS)->map(fn (string $title, string $slug) => [
                'slug' => $slug, 'title' => $title, 'url' => $channel->overlayUrl($slug),
            ])->values(),
        ];
    }
}

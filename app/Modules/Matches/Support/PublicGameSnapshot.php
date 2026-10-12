<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Rules\Sheet\GameClock;
use App\Modules\Teams\Models\Person;

/**
 * Retrato PÚBLICO do jogo (placar, relógio e gols), usado no snapshot da página ao vivo e nos deltas do Reverb.
 * LGPD: cartões, disciplina, escalação e dados pessoais NÃO entram aqui; do autor do gol só o apelido/nome.
 */
final class PublicGameSnapshot
{
    /** @return array<string, mixed> */
    public static function for(Game $game): array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team', 'venue', 'stage', 'division.season.competition']);

        $goals = $game->events()->effective()
            ->whereIn('type', [GameEventType::Goal, GameEventType::OwnGoal])
            ->orderBy('id')
            ->get();

        $names = Person::query()
            ->whereIn('id', $goals->pluck('person_id')->filter()->unique())
            ->get(['id', 'name', 'nickname', 'birth_date'])
            ->mapWithKeys(fn (Person $person) => [$person->id => PublicName::of($person)]);

        $marks = ChangeGamePeriod::marks($game);

        $side = fn (?string $entryId): ?string => $entryId === null ? null : ($entryId === $game->home_entry_id ? 'home' : ($entryId === $game->away_entry_id ? 'away' : null));
        $team = fn ($entry): ?array => $entry === null ? null : ['name' => $entry->team->name, 'short_name' => $entry->team->short_name, 'logo_url' => $entry->team->logoUrl(512), 'color' => $entry->team->primary_color];

        return [
            'id' => $game->getKey(),
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'finished' => $game->status->isDecided(),
            'home' => $team($game->homeEntry),
            'away' => $team($game->awayEntry),
            'home_score' => $game->home_score,
            'away_score' => $game->away_score,
            'home_penalties' => $game->home_penalties,
            'away_penalties' => $game->away_penalties,
            'scheduled_at' => $game->scheduled_at?->toIso8601String(),
            'venue' => $game->venue?->name,
            'competition' => $game->division->season->competition->name,
            'division' => $game->division->name,
            'stage' => $game->stage->name,
            'clock' => (new GameClock)->snapshot($marks),
            'goals' => $goals->map(function (GameEvent $event) use ($side, $names) {
                $own = $event->type === GameEventType::OwnGoal;
                $scoringSide = $side($event->entry_id);

                return [
                    // Gol contra conta para o adversário de quem o fez.
                    'side' => $own ? ($scoringSide === 'home' ? 'away' : 'home') : $scoringSide,
                    'player' => $event->person_id ? ($names[$event->person_id] ?? null) : null,
                    'minute' => $event->minute,
                    'period' => (int) $event->period,
                    'own_goal' => $own,
                ];
            })->values()->all(),
            'server_time_ms' => (int) (microtime(true) * 1000),
        ];
    }
}

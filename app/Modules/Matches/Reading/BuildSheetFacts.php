<?php

namespace App\Modules\Matches\Reading;

use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Teams\Models\Person;

/**
 * Monta, para a conferência, o que o sistema tem do jogo: placar, escalação (sem comissão) e lances válidos
 * (os anulados ficam de fora). Só leitura — nada aqui altera o jogo.
 *
 * @phpstan-import-type Facts from SheetComparison
 */
final class BuildSheetFacts
{
    private const EVENT_TYPES = ['goal', 'own_goal', 'yellow_card', 'red_card', 'substitution', 'foul', 'timeout'];

    /**
     * @return Facts
     */
    public function handle(Game $game): array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);

        $side = fn (?string $entryId): ?string => $entryId === null ? null : ($entryId === $game->home_entry_id ? 'home' : ($entryId === $game->away_entry_id ? 'away' : null));

        $lineupRows = GameLineup::query()->where('game_id', $game->getKey())->where('slot', '!=', LineupSlot::Staff->value)->with('person')->orderBy('sort')->get();
        $shirts = [];
        $lineups = [];

        foreach ($lineupRows as $row) {
            $shirts[$row->entry_id.'|'.$row->person_id] = $row->shirt_number;
            $team = $side($row->entry_id);

            if ($team !== null) {
                $lineups[] = [
                    'team' => $team,
                    'name' => $row->person?->name,
                    'shirt' => $row->shirt_number,
                    'starter' => $row->slot === LineupSlot::Starter,
                ];
            }
        }

        $shirtOf = fn (GameEvent $event, ?Person $person): ?int => $person === null ? null : ($shirts[$event->entry_id.'|'.$person->getKey()] ?? null);

        $events = [];

        foreach (GameEvent::query()->effective()->where('game_id', $game->getKey())->whereIn('type', self::EVENT_TYPES)->with(['person', 'secondaryPerson'])->orderBy('created_at')->get() as $event) {
            $team = $side($event->entry_id);

            if ($team === null) {
                continue;
            }

            // Substituição: no sistema `person` sai e `secondaryPerson` entra; na súmula "nome" é quem entra.
            $main = $event->type->value === 'substitution' ? $event->secondaryPerson : $event->person;
            $other = $event->type->value === 'substitution' ? $event->person : null;

            $events[] = [
                'type' => $event->type->value,
                'team' => $team,
                'name' => $main?->name,
                'shirt' => $shirtOf($event, $main),
                'minute' => $event->minute !== null ? (string) $event->minute : null,
                'detail' => $other?->name,
            ];
        }

        return [
            'home_team' => $game->homeEntry?->team->name,
            'away_team' => $game->awayEntry?->team->name,
            'home_score' => (int) $game->home_score,
            'away_score' => (int) $game->away_score,
            'lineups' => $lineups,
            'events' => $events,
        ];
    }
}

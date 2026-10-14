<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Rules\Sheet\GameSheetState;
use App\Modules\Rules\Sheet\SheetEvent;
use InvalidArgumentException;

/**
 * Monta o estado da súmula (quem está em campo, expulsos, substituições) a partir da escalação e dos
 * eventos válidos do jogo, reaplicando-os em ordem. `$without` simula a anulação de um evento: se os
 * demais deixarem de ser coerentes (ex.: gol de quem só entrou na substituição anulada), lança exceção
 * com a regra violada.
 */
final class BuildGameSheet
{
    public function handle(Game $game, ?string $without = null): GameSheetState
    {
        $state = new GameSheetState($game->division->ruleSet());

        $lineups = $game->lineups()->get()->groupBy('entry_id');

        foreach ($lineups as $entryId => $rows) {
            $ids = fn (LineupSlot $slot): array => $rows->where('slot', $slot)->pluck('person_id')->map(fn ($id) => (string) $id)->values()->all();

            $positions = $rows->where('slot', '!=', LineupSlot::Staff)->pluck('position', 'person_id')->map(fn ($position): ?string => $position === null ? null : (string) $position)->all();
            $state->setLineup((string) $entryId, $ids(LineupSlot::Starter), $ids(LineupSlot::Bench), $ids(LineupSlot::Staff), $positions);
        }

        $events = $game->events()->effective()->orderBy('id')->get();

        foreach ($events as $event) {
            // Eventos anônimos (resultado rápido) não têm atleta e não passam pela súmula.
            if ($event->getKey() === $without || $event->entry_id === null || $event->person_id === null) {
                continue;
            }

            $error = $state->tryApply(new SheetEvent(
                $event->type,
                $event->entry_id,
                $event->person_id,
                $event->secondary_person_id,
                (int) $event->period,
                $event->minute,
            ));

            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }
        }

        return $state;
    }
}

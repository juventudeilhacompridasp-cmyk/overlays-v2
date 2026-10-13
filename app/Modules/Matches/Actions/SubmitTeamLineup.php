<?php

namespace App\Modules\Matches\Actions;

use App\Models\User;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Matches\Support\ManagerLineupWindow;
use App\Modules\Rules\Tactics\Formations;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Escalação e esquema tático definidos pelo gestor da equipe (ADR 0003): formação, posição de cada titular, capitão,
 * reservas, comissão e instruções privadas. As regras da escalação (elenco ativo, limites, suspensos, travas) continuam
 * sendo as de `SaveGameLineup`, que é quem grava; aqui entram a janela do gestor, a coerência com a formação e a confirmação.
 * Salvar sem confirmar é rascunho; confirmar exige a formação completa e o capitão.
 */
final class SubmitTeamLineup
{
    public function __construct(private readonly SaveGameLineup $save = new SaveGameLineup) {}

    /**
     * @param  list<string>  $starters
     * @param  list<string>  $bench
     * @param  list<string>  $staff
     * @param  array<string, string>  $positions  pessoa => vaga da formação
     */
    public function handle(
        Game $game,
        Entry $entry,
        User $by,
        string $formationCode,
        array $starters,
        array $bench,
        array $staff,
        array $positions,
        ?string $captain,
        ?string $notes,
        bool $confirm,
    ): GameTactic {
        $reason = ManagerLineupWindow::closedReason($game);

        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }

        $formation = Formations::find($game->sport()->profile()->key, $formationCode)
            ?? throw new InvalidArgumentException('Formação inválida para este esporte.');

        $starterPositions = array_intersect_key($positions, array_flip($starters));
        $benchPositions = array_intersect_key($positions, array_flip($bench));

        $error = Formations::validate($formation, $starterPositions, $benchPositions);

        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        if ($confirm) {
            $fieldSlots = count(array_filter($formation->slots, fn ($slot) => ! $slot->bench));

            if (count($starters) !== $fieldSlots || count($starterPositions) !== $fieldSlots) {
                throw new InvalidArgumentException("Para confirmar, escale {$fieldSlots} titulares, cada um em uma posição da formação.");
            }

            if ($captain === null) {
                throw new InvalidArgumentException('Para confirmar, escolha o capitão.');
            }
        }

        return DB::transaction(function () use ($game, $entry, $by, $formationCode, $starters, $bench, $staff, $starterPositions, $benchPositions, $captain, $notes, $confirm): GameTactic {
            $this->save->handle($game, $entry, $starters, $bench, $staff, [...$starterPositions, ...$benchPositions], $captain);

            return GameTactic::query()->updateOrCreate(
                ['game_id' => $game->getKey(), 'entry_id' => $entry->getKey()],
                [
                    'organization_id' => $game->organization_id,
                    'formation' => $formationCode,
                    'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                    'updated_by' => $by->getKey(),
                    'confirmed_at' => $confirm ? now() : null,
                    'confirmed_by' => $confirm ? $by->getKey() : null,
                ],
            );
        });
    }
}

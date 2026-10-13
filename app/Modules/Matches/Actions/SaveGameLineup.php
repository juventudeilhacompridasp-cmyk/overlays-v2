<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Define a escalação de uma equipe no jogo (substitui a anterior). Só aceita atletas ativos do elenco
 * (titulares/reservas) e membros da comissão; respeita os limites do regulamento e não permite escalar
 * atleta suspenso. Depois de lançados eventos da equipe, a escalação fica travada.
 */
final class SaveGameLineup
{
    public function __construct(private readonly CalculateDiscipline $discipline = new CalculateDiscipline) {}

    /**
     * A ordem das listas vira a ordem na escalação. `$positions` (pessoa => vaga da formação) e `$captain` vêm do esquema
     * tático; quando omitidos (`null`, ex.: o mesário salvando só os ids), mantém posição e capitão de quem continua na
     * escalação. Se as pessoas/funções mudarem, a confirmação do gestor (`game_tactics`) é desfeita.
     *
     * @param  list<string>  $starters
     * @param  list<string>  $bench
     * @param  list<string>  $staff
     * @param  array<string, string>|null  $positions
     */
    public function handle(Game $game, Entry $entry, array $starters, array $bench = [], array $staff = [], ?array $positions = null, ?string $captain = null): void
    {
        if ($entry->getKey() !== $game->home_entry_id && $entry->getKey() !== $game->away_entry_id) {
            throw new InvalidArgumentException('A equipe não participa deste jogo.');
        }

        if ($game->status->isDecided()) {
            throw new InvalidArgumentException('O jogo já foi encerrado. Reabra para alterar a escalação.');
        }

        if ($game->events()->effective()->where('entry_id', $entry->getKey())->exists()) {
            throw new InvalidArgumentException('Já há lançamentos desta equipe na súmula; a escalação não pode mais ser alterada.');
        }

        $rules = $game->division->ruleSet();
        $all = [...$starters, ...$bench, ...$staff];

        if (count($all) !== count(array_unique($all))) {
            throw new InvalidArgumentException('Uma pessoa não pode aparecer duas vezes na escalação.');
        }

        if (count($starters) > $rules->maxStarters) {
            throw new InvalidArgumentException("No máximo {$rules->maxStarters} titulares.");
        }

        if (count($bench) > $rules->maxBench) {
            throw new InvalidArgumentException("No máximo {$rules->maxBench} reservas.");
        }

        if (count($staff) > $rules->maxStaff) {
            throw new InvalidArgumentException("No máximo {$rules->maxStaff} membros da comissão.");
        }

        /** @var Collection<string, RosterEntry> $roster */
        $roster = RosterEntry::query()
            ->where('entry_id', $entry->getKey())
            ->where('status', RosterStatus::Active)
            ->whereIn('person_id', $all)
            ->get()
            ->keyBy('person_id');

        $suspended = $this->discipline->forEntry($entry)->suspended();

        foreach ([[$starters, LineupSlot::Starter], [$bench, LineupSlot::Bench], [$staff, LineupSlot::Staff]] as [$people, $slot]) {
            foreach ($people as $personId) {
                $row = $roster->get($personId);

                if ($row === null) {
                    throw new InvalidArgumentException('Há pessoas que não pertencem ao elenco ativo da equipe.');
                }

                if (($slot === LineupSlot::Staff) === ($row->role === RosterRole::Athlete)) {
                    throw new InvalidArgumentException($slot === LineupSlot::Staff
                        ? 'Atletas não entram como comissão.'
                        : 'Só atletas podem ser titulares ou reservas.');
                }

                if ($slot !== LineupSlot::Staff && ($suspended[$personId] ?? 0) > 0) {
                    throw new InvalidArgumentException('Há atleta suspenso na escalação.');
                }
            }
        }

        if ($captain !== null && ! in_array($captain, [...$starters, ...$bench], true)) {
            throw new InvalidArgumentException('O capitão precisa estar entre os titulares ou reservas.');
        }

        DB::transaction(function () use ($game, $entry, $starters, $bench, $staff, $roster, $positions, $captain): void {
            $existing = GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->get();
            $before = $existing->map(fn (GameLineup $row) => $row->slot->value.':'.$row->person_id)->sort()->values()->all();

            if ($positions === null) {
                // Só segue valendo a posição de quem continua na mesma função (titular que virou reserva perde a vaga).
                $newSlot = [...array_fill_keys($starters, LineupSlot::Starter), ...array_fill_keys($bench, LineupSlot::Bench)];
                $kept = $existing->filter(fn (GameLineup $row) => ($newSlot[$row->person_id] ?? null) === $row->slot);
                $positions = $kept->whereNotNull('position')->pluck('position', 'person_id')->all();
                $captain = $kept->firstWhere('is_captain', true)?->person_id;
            }

            GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->delete();

            foreach ([[$starters, LineupSlot::Starter], [$bench, LineupSlot::Bench], [$staff, LineupSlot::Staff]] as [$people, $slot]) {
                foreach (array_values($people) as $index => $personId) {
                    GameLineup::query()->create([
                        'organization_id' => $game->organization_id,
                        'game_id' => $game->getKey(),
                        'entry_id' => $entry->getKey(),
                        'person_id' => $personId,
                        'slot' => $slot,
                        'position' => $slot === LineupSlot::Staff ? null : ($positions[$personId] ?? null),
                        'sort' => $index,
                        'is_captain' => $slot !== LineupSlot::Staff && $captain === $personId,
                        'shirt_number' => $roster->get($personId)?->shirt_number,
                    ]);
                }
            }

            $after = collect([[$starters, LineupSlot::Starter], [$bench, LineupSlot::Bench], [$staff, LineupSlot::Staff]])
                ->flatMap(fn (array $group) => array_map(fn (string $id) => $group[1]->value.':'.$id, $group[0]))->sort()->values()->all();

            if ($before !== $after) {
                GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->update(['confirmed_at' => null, 'confirmed_by' => null]);
            }
        });
    }
}

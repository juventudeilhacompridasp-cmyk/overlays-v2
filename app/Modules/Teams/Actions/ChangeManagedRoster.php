<?php

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterChange;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Support\ManagedTeams;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Alterações de elenco que o gestor da equipe faz sozinho (ADR 0003): camisa, apelido e desligamento. Tudo dentro da
 * janela da edição (`Season::rosterChangesClosedReason`), pelas mesmas regras do cadastro do painel (camisa única entre
 * atletas ativos, etc.) e com registro no histórico. A inclusão de pessoas novas NÃO passa por aqui: é o pedido de reforço,
 * que a organização analisa.
 */
final class ChangeManagedRoster
{
    public function __construct(private readonly AddToRoster $roster = new AddToRoster, private readonly RemoveFromRoster $remove = new RemoveFromRoster) {}

    public function changeShirt(RosterEntry $row, ?int $shirt, User $by): void
    {
        $this->ensureAllowed($row);

        if ($row->role !== RosterRole::Athlete) {
            throw new InvalidArgumentException('Só atletas têm número de camisa.');
        }

        if ($row->shirt_number === $shirt) {
            return;
        }

        try {
            $this->roster->ensureAllowed($row->entry, $row->person, $row->role, $shirt, $row->status, $row);
        } catch (ValidationException $exception) {
            throw new InvalidArgumentException(collect($exception->errors())->flatten()->first() ?? 'Camisa indisponível.');
        }

        $from = $row->shirt_number;
        $row->update(['shirt_number' => $shirt]);

        RosterChange::record($row->entry_id, $row->organization_id, RosterChange::SHIRT, $row->person_id, $by->getKey(), ['name' => $row->person->name, 'from' => $from, 'to' => $shirt]);
    }

    public function changeNickname(RosterEntry $row, ?string $nickname, User $by): void
    {
        $this->ensureAllowed($row);

        $nickname = $nickname !== null && trim($nickname) !== '' ? trim($nickname) : null;
        $person = $row->person;

        if ($person->nickname === $nickname) {
            return;
        }

        // A pessoa é única na organização: se ela também está ativa em equipe de outro gestor, o apelido não é só desta equipe.
        $elsewhere = RosterEntry::query()
            ->where('person_id', $person->getKey())
            ->where('status', RosterStatus::Active)
            ->whereHas('entry', fn ($query) => $query->whereNotIn('team_id', ManagedTeams::ids($by)))
            ->exists();

        if ($elsewhere) {
            throw new InvalidArgumentException('Esta pessoa também está em outra equipe; peça à organização para alterar o apelido.');
        }

        $from = $person->nickname;
        $person->update(['nickname' => $nickname]);

        RosterChange::record($row->entry_id, $row->organization_id, RosterChange::NICKNAME, $person->getKey(), $by->getKey(), ['name' => $person->name, 'from' => $from, 'to' => $nickname]);
    }

    /**
     * Desliga a pessoa do elenco (com histórico de jogos ela fica como "removida") e a tira das escalações dos jogos
     * ainda não iniciados, desfazendo a confirmação dessas escalações.
     */
    public function release(RosterEntry $row, User $by): void
    {
        $this->ensureAllowed($row);

        DB::transaction(function () use ($row, $by): void {
            $upcoming = GameLineup::query()
                ->where('entry_id', $row->entry_id)
                ->where('person_id', $row->person_id)
                ->whereHas('game', fn ($query) => $query->where('status', GameStatus::Scheduled));

            $gameIds = (clone $upcoming)->pluck('game_id');

            $upcoming->delete();

            GameTactic::query()->where('entry_id', $row->entry_id)->whereIn('game_id', $gameIds)->update(['confirmed_at' => null, 'confirmed_by' => null]);

            $name = $row->person->name;
            $personId = $row->person_id;
            $role = $row->role->label();
            $entryId = $row->entry_id;
            $organizationId = $row->organization_id;

            $deleted = $this->remove->handle($row);

            RosterChange::record($entryId, $organizationId, RosterChange::RELEASED, $personId, $by->getKey(), ['name' => $name, 'role' => $role, 'kept_as_removed' => ! $deleted]);
        });
    }

    private function ensureAllowed(RosterEntry $row): void
    {
        $row->loadMissing('entry.division.season', 'person');

        if ($row->status !== RosterStatus::Active) {
            throw new InvalidArgumentException('Esta pessoa não está mais no elenco.');
        }

        $reason = $row->entry->division->season->rosterChangesClosedReason();

        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }
    }
}

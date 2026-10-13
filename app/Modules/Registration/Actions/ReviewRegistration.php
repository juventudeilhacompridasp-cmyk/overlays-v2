<?php

namespace App\Modules\Registration\Actions;

use App\Models\User;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Enums\ReviewStatus;
use App\Modules\Registration\Events\SupplementApproved;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Teams\Actions\AddToRoster;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterChange;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Decisões da organização sobre um pedido enviado: aprovar/recusar cada pessoa, devolver para ajustes,
 * recusar o pedido ou aprová-lo. Aprovar cria a equipe (ou usa uma existente), a inscrição confirmada e o elenco,
 * pelas mesmas regras do cadastro manual (camisa única, um clube por divisão); se uma regra falhar, nada é criado.
 */
final class ReviewRegistration
{
    public function __construct(private readonly AddToRoster $roster = new AddToRoster) {}

    public function reviewPerson(RegistrationPerson $person, ReviewStatus $status, ?string $note = null): RegistrationPerson
    {
        $this->ensureSubmitted($person->registration);

        if ($status === ReviewStatus::Rejected && trim((string) $note) === '') {
            throw new InvalidArgumentException('Explique o motivo da recusa para a equipe poder corrigir.');
        }

        $person->update(['review_status' => $status, 'review_note' => $status === ReviewStatus::Rejected ? trim((string) $note) : null]);

        return $person;
    }

    public function returnForChanges(Registration $registration, string $note, User $reviewer): Registration
    {
        $this->ensureSubmitted($registration);

        if (trim($note) === '') {
            throw new InvalidArgumentException('Explique o que a equipe precisa ajustar.');
        }

        $registration->update(['status' => RegistrationStatus::Returned, 'review_note' => trim($note), 'reviewed_at' => now(), 'reviewed_by' => $reviewer->getKey()]);

        return $registration;
    }

    public function reject(Registration $registration, string $note, User $reviewer): Registration
    {
        $this->ensureSubmitted($registration);

        if (trim($note) === '') {
            throw new InvalidArgumentException('Explique o motivo da recusa.');
        }

        $registration->update(['status' => RegistrationStatus::Rejected, 'review_note' => trim($note), 'reviewed_at' => now(), 'reviewed_by' => $reviewer->getKey()]);

        return $registration;
    }

    /** @param  string|null  $teamId  vincula a uma equipe já cadastrada em vez de criar uma nova */
    public function approve(Registration $registration, User $reviewer, ?string $teamId = null): Entry
    {
        if ($registration->isSupplement()) {
            return $this->approveSupplement($registration, $reviewer);
        }

        $this->ensureSubmitted($registration);

        $people = $registration->people()->get();

        if ($people->contains(fn (RegistrationPerson $person) => $person->review_status === ReviewStatus::Pending)) {
            throw new InvalidArgumentException('Revise todos os atletas e membros da comissão antes de aprovar.');
        }

        $approved = $people->filter(fn (RegistrationPerson $person) => $person->review_status === ReviewStatus::Approved);
        $min = $registration->season->registrationSettings()['min_athletes'];

        if ($approved->where('role', RosterRole::Athlete)->count() < $min) {
            throw new InvalidArgumentException("É preciso aprovar pelo menos {$min} atletas para aprovar a inscrição.");
        }

        return DB::transaction(function () use ($registration, $reviewer, $teamId, $approved): Entry {
            $team = $this->team($registration, $teamId);

            $exists = Entry::query()->where('division_id', $registration->division_id)->where('team_id', $team->getKey())->exists();

            if ($exists) {
                throw new InvalidArgumentException("{$team->name} já tem inscrição nesta divisão.");
            }

            $entry = Entry::query()->create([
                'organization_id' => $registration->organization_id,
                'division_id' => $registration->division_id,
                'team_id' => $team->getKey(),
                'status' => EntryStatus::Confirmed,
            ]);

            foreach ($approved as $item) {
                $person = $this->person($registration, $item);

                try {
                    $this->roster->handle($entry, $person, $item->role, $item->shirt_number);
                } catch (ValidationException $exception) {
                    throw new InvalidArgumentException(collect($exception->errors())->flatten()->first() ?? 'Regra do elenco violada.');
                }

                $item->update(['person_id' => $person->getKey()]);
            }

            $registration->update([
                'status' => RegistrationStatus::Approved,
                'team_id' => $team->getKey(),
                'entry_id' => $entry->getKey(),
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->getKey(),
                'review_note' => null,
            ]);

            return $entry;
        });
    }

    /** Reforço: inclui as pessoas aprovadas no elenco da inscrição que já existe (nada de equipe ou inscrição nova). */
    private function approveSupplement(Registration $registration, User $reviewer): Entry
    {
        $this->ensureSubmitted($registration);

        $people = $registration->people()->get();

        if ($people->contains(fn (RegistrationPerson $person) => $person->review_status === ReviewStatus::Pending)) {
            throw new InvalidArgumentException('Revise todas as pessoas do pedido antes de aprovar.');
        }

        $approved = $people->filter(fn (RegistrationPerson $person) => $person->review_status === ReviewStatus::Approved);

        if ($approved->isEmpty()) {
            throw new InvalidArgumentException('Aprove pelo menos uma pessoa para incluir no elenco (ou recuse o pedido).');
        }

        $entry = Entry::query()->findOrFail($registration->entry_id);
        $max = $registration->season->registrationSettings()['max_athletes'];
        $current = RosterEntry::query()->where('entry_id', $entry->getKey())->where('role', RosterRole::Athlete)->where('status', RosterStatus::Active)->count();

        if ($current + $approved->where('role', RosterRole::Athlete)->count() > $max) {
            throw new InvalidArgumentException("O elenco passaria do máximo de {$max} atletas da edição.");
        }

        $result = DB::transaction(function () use ($registration, $reviewer, $approved, $entry): Entry {
            foreach ($approved as $item) {
                $person = $this->person($registration, $item);

                try {
                    $this->roster->handle($entry, $person, $item->role, $item->shirt_number);
                } catch (ValidationException $exception) {
                    throw new InvalidArgumentException(collect($exception->errors())->flatten()->first() ?? 'Regra do elenco violada.');
                }

                $item->update(['person_id' => $person->getKey()]);
            }

            $registration->update(['status' => RegistrationStatus::Approved, 'reviewed_at' => now(), 'reviewed_by' => $reviewer->getKey(), 'review_note' => null]);

            RosterChange::record($entry->getKey(), $entry->organization_id, RosterChange::SUPPLEMENT_APPROVED, null, $reviewer->getKey(), ['people' => $approved->count()]);

            return $entry;
        });

        // Fora da transação: quem escuta (ex.: Mercado) só vê o reforço já aprovado.
        event(new SupplementApproved($registration->getKey()));

        return $result;
    }

    private function team(Registration $registration, ?string $teamId): Team
    {
        if ($teamId !== null && $teamId !== '') {
            return Team::query()->findOrFail($teamId);
        }

        $taken = Team::query()->whereRaw('lower(name) = ?', [mb_strtolower($registration->team_name)])->exists();

        if ($taken) {
            throw new InvalidArgumentException("Já existe uma equipe chamada \"{$registration->team_name}\". Vincule o pedido a ela ou peça à equipe para usar outro nome.");
        }

        return Team::query()->create([
            'organization_id' => $registration->organization_id,
            'name' => $registration->team_name,
            'short_name' => $registration->team_short_name,
            'city' => $registration->team_city,
        ]);
    }

    /** Reaproveita o cadastro existente (mesmo CPF) em vez de duplicar a pessoa. */
    private function person(Registration $registration, RegistrationPerson $item): Person
    {
        if ($item->document_hash !== null) {
            $existing = Person::query()->where('document_hash', $item->document_hash)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return Person::query()->create([
            'organization_id' => $registration->organization_id,
            'name' => $item->name,
            'nickname' => $item->nickname,
            'birth_date' => $item->birth_date,
            'document_hash' => $item->document_hash,
        ]);
    }

    private function ensureSubmitted(Registration $registration): void
    {
        if ($registration->status !== RegistrationStatus::Submitted) {
            throw new InvalidArgumentException('Só pedidos em análise podem ser revisados.');
        }
    }
}

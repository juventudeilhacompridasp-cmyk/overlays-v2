<?php

namespace App\Modules\Registration\Actions;

use App\Modules\Registration\Enums\ReviewStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Registration\Support\EnsureEditable;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Support\Cpf;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Inclui ou altera um atleta/membro da comissão no pedido de inscrição.
 *
 * Regras: atleta precisa de data de nascimento (para saber se é menor); menor exige responsável legal e a
 * declaração de autorização; camisa única entre os atletas do pedido; CPF válido e sem repetição no pedido
 * (guardado só como hash); limite de atletas da edição.
 */
final class SaveRegistrationPerson
{
    /**
     * @param  array{role: string, name: string, nickname?: string|null, birth_date?: string|null, document?: string|null, shirt_number?: int|string|null, guardian_name?: string|null, guardian_consent?: bool|null}  $data
     */
    public function handle(Registration $registration, array $data, ?RegistrationPerson $person = null): RegistrationPerson
    {
        EnsureEditable::check($registration);

        $role = RosterRole::from($data['role']);
        $isAthlete = $role === RosterRole::Athlete;
        $birth = ! empty($data['birth_date']) ? Carbon::parse($data['birth_date']) : null;

        if ($isAthlete && $birth === null) {
            throw ValidationException::withMessages(['birth_date' => 'Informe a data de nascimento do atleta.']);
        }

        if ($isAthlete && $person === null) {
            $max = $registration->season->registrationSettings()['max_athletes'];

            if ($registration->people()->where('role', RosterRole::Athlete)->count() >= $max) {
                throw ValidationException::withMessages(['name' => "O limite é de {$max} atletas por equipe nesta edição."]);
            }
        }

        $others = $registration->people()->when($person !== null, fn ($query) => $query->whereKeyNot($person?->getKey()));
        $shirt = $isAthlete && ($data['shirt_number'] ?? '') !== '' ? (int) $data['shirt_number'] : null;

        if ($shirt !== null && (clone $others)->where('role', RosterRole::Athlete)->where('shirt_number', $shirt)->exists()) {
            throw ValidationException::withMessages(['shirt_number' => "A camisa {$shirt} já está com outro atleta."]);
        }

        $hash = $person?->document_hash;
        $document = (string) ($data['document'] ?? '');

        if ($document !== '') {
            if (! Cpf::isValid($document)) {
                throw ValidationException::withMessages(['document' => 'O CPF informado não é válido.']);
            }

            $hash = Cpf::hash($document);

            if ((clone $others)->where('document_hash', $hash)->where('role', $role)->exists()) {
                throw ValidationException::withMessages(['document' => 'Este CPF já foi informado neste pedido.']);
            }
        }

        $minor = $birth !== null && $birth->age < 18;
        $guardian = trim((string) ($data['guardian_name'] ?? ''));
        $consent = (bool) ($data['guardian_consent'] ?? false);

        if ($minor && ($guardian === '' || ! $consent)) {
            throw ValidationException::withMessages(['guardian_name' => 'Menor de idade: informe o responsável legal e confirme a autorização.']);
        }

        $attributes = [
            'role' => $role,
            'name' => trim($data['name']),
            'nickname' => $data['nickname'] ?? null,
            'birth_date' => $birth,
            'document_hash' => $hash,
            'shirt_number' => $shirt,
            'guardian_name' => $minor ? $guardian : null,
            'guardian_consent_at' => $minor ? ($person?->guardian_consent_at ?? now()) : null,
        ];

        if ($person === null) {
            return RegistrationPerson::query()->create($attributes + [
                'organization_id' => $registration->organization_id,
                'registration_id' => $registration->getKey(),
                'review_status' => ReviewStatus::Pending,
            ]);
        }

        $person->update($attributes);

        return $person;
    }
}

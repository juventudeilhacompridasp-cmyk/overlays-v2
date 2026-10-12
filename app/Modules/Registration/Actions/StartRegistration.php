<?php

namespace App\Modules\Registration\Actions;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Teams\Models\Team;
use InvalidArgumentException;

/**
 * Abre o pedido de inscrição de uma equipe numa divisão. Só com a edição em "Inscrições abertas" e dentro do prazo.
 * O responsável recebe um link pessoal (token) para completar elenco e documentos.
 */
final class StartRegistration
{
    /**
     * @param  array{division_id: string, team_name: string, team_short_name?: string|null, team_city?: string|null, contact_name: string, contact_email: string, contact_phone?: string|null}  $data
     */
    public function handle(Season $season, array $data): Registration
    {
        if (! $season->registrationOpen()) {
            throw new InvalidArgumentException('As inscrições desta edição não estão abertas.');
        }

        $division = $season->divisions()->whereKey($data['division_id'])->first();

        if ($division === null) {
            throw new InvalidArgumentException('Escolha uma divisão desta edição.');
        }

        $name = trim($data['team_name']);

        $duplicate = Registration::query()
            ->where('division_id', $division->getKey())
            ->where('status', '!=', RegistrationStatus::Rejected->value)
            ->whereRaw('lower(team_name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Já existe um pedido de inscrição desta equipe nesta divisão. Use o link que foi enviado ao responsável.');
        }

        $alreadyIn = Entry::query()
            ->where('division_id', $division->getKey())
            ->where('status', '!=', EntryStatus::Withdrawn->value)
            ->whereIn('team_id', Team::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->select('id'))
            ->exists();

        if ($alreadyIn) {
            throw new InvalidArgumentException('Esta equipe já está inscrita nesta divisão.');
        }

        return Registration::query()->create([
            'organization_id' => $season->organization_id,
            'season_id' => $season->getKey(),
            'division_id' => $division->getKey(),
            'token' => Registration::newToken(),
            'status' => RegistrationStatus::Draft,
            'team_name' => $name,
            'team_short_name' => $data['team_short_name'] ?? null,
            'team_city' => $data['team_city'] ?? null,
            'contact_name' => trim($data['contact_name']),
            'contact_email' => mb_strtolower(trim($data['contact_email'])),
            'contact_phone' => $data['contact_phone'] ?? null,
            'consent_at' => now(),
        ]);
    }
}

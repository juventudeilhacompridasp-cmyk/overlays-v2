<?php

namespace App\Modules\Registration\Actions;

use App\Models\User;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Tenancy\Enums\LegalType;
use App\Modules\Tenancy\Support\LegalDocuments;
use InvalidArgumentException;

/**
 * Abre (ou retoma) o pedido de REFORÇO de uma inscrição já aprovada (ADR 0003): o gestor da equipe pede a inclusão de
 * atletas e comissão pelo mesmo fluxo da inscrição (cadastro, documentos, autorização do responsável de menores, análise
 * da organização). Só existe um pedido aberto por vez para a mesma inscrição; respeita a janela de alterações da edição.
 */
final class StartSupplement
{
    public function handle(Entry $entry, User $requestedBy): Registration
    {
        $entry->loadMissing(['team', 'division.season']);

        if ($entry->status === EntryStatus::Withdrawn) {
            throw new InvalidArgumentException('Esta inscrição foi retirada da competição.');
        }

        $season = $entry->division->season;
        $reason = $season->rosterChangesClosedReason();

        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }

        $open = Registration::query()
            ->where('entry_id', $entry->getKey())
            ->where('kind', Registration::KIND_SUPPLEMENT)
            ->whereIn('status', [RegistrationStatus::Draft, RegistrationStatus::Returned, RegistrationStatus::Submitted])
            ->first();

        if ($open !== null) {
            return $open;
        }

        return Registration::query()->create([
            'organization_id' => $entry->organization_id,
            'season_id' => $season->getKey(),
            'division_id' => $entry->division_id,
            'token' => Registration::newToken(),
            'status' => RegistrationStatus::Draft,
            'kind' => Registration::KIND_SUPPLEMENT,
            'requested_by' => $requestedBy->getKey(),
            'team_name' => $entry->team->name,
            'team_short_name' => $entry->team->short_name,
            'team_city' => $entry->team->city,
            'contact_name' => $requestedBy->name,
            'contact_email' => mb_strtolower($requestedBy->email),
            'team_id' => $entry->team_id,
            'entry_id' => $entry->getKey(),
            // O gestor já aceitou os termos e a privacidade vigentes para entrar na área dele.
            'consent_at' => now(),
            'consent_document_id' => LegalDocuments::current(LegalType::Privacy)?->getKey(),
        ]);
    }
}

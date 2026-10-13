<?php

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\Models\LegalAcceptance;
use App\Modules\Tenancy\Support\LegalDocuments;

/** Registra o aceite do usuário às versões pendentes (as mais recentes ainda não aceitas). */
final class AcceptLegalDocuments
{
    public function handle(User $user): int
    {
        $pending = LegalDocuments::pendingFor($user);

        foreach ($pending as $document) {
            LegalAcceptance::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'legal_document_id' => $document->getKey()],
                ['accepted_at' => now()],
            );
        }

        return $pending->count();
    }
}

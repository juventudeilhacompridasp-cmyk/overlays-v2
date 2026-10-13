<?php

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\Enums\LegalType;
use App\Modules\Tenancy\Models\LegalAcceptance;
use App\Modules\Tenancy\Models\LegalDocument;
use App\Modules\Tenancy\Support\LegalDocuments;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Publica uma nova versão de um texto legal (as anteriores ficam guardadas). Todos os usuários do painel precisarão aceitá-la. */
final class PublishLegalDocument
{
    public function handle(LegalType $type, string $body, ?User $by = null): LegalDocument
    {
        $body = trim(str_replace("\r\n", "\n", $body));

        if ($body === '') {
            throw new InvalidArgumentException('O texto não pode ficar em branco.');
        }

        return DB::transaction(function () use ($type, $body, $by): LegalDocument {
            $current = LegalDocuments::current($type);

            if ($current !== null && $current->body === $body) {
                throw new InvalidArgumentException('O texto não mudou em relação à versão publicada.');
            }

            $document = LegalDocument::query()->create([
                'type' => $type,
                'version' => ($current->version ?? 0) + 1,
                'body' => $body,
                'published_by' => $by?->getKey(),
                'published_at' => now(),
            ]);

            // Quem publica o texto já o conhece: conta como aceite.
            if ($by !== null) {
                LegalAcceptance::query()->create(['user_id' => $by->getKey(), 'legal_document_id' => $document->getKey(), 'accepted_at' => now()]);
            }

            return $document;
        });
    }
}

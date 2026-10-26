<?php

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Models\DocumentAccess;
use Illuminate\Support\Facades\Auth;

/**
 * Trilha de quem abriu documentos pessoais (LGPD): quem, quando e qual documento (tipo e pessoa, nunca CPF nem o arquivo).
 * Sem IP. Quem abre documento chama `record` ANTES de redirecionar para o link assinado; falha de gravação nunca impede abrir.
 */
final class DocumentAccessLog
{
    public const KINDS = [
        'registration_document' => 'Documento da inscrição',
        'person_document' => 'Documento da pessoa',
        'sheet_upload' => 'Súmula enviada',
    ];

    public static function record(string $kind, string $label): void
    {
        try {
            $user = Auth::user();

            DocumentAccess::query()->create([
                'user_id' => $user instanceof User ? $user->getKey() : null,
                'actor_name' => mb_substr($user instanceof User ? $user->name : 'Desconhecido', 0, 120),
                'kind' => array_key_exists($kind, self::KINDS) ? $kind : 'person_document',
                'label' => mb_substr($label, 0, 200),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

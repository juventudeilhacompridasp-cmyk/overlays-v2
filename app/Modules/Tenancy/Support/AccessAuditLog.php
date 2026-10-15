<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Models\AccessAudit;
use Illuminate\Support\Facades\Auth;

/** Grava a trilha de auditoria de acesso (ADR 0005): quem fez, em quem, e um resumo em português. Nunca guarda IP. */
final class AccessAuditLog
{
    /**
     * Texto do que mudou entre duas listas de permissões (rótulos em português).
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public static function diff(array $before, array $after): string
    {
        $label = fn (string $value): string => Permission::tryFrom($value)?->label() ?? $value;
        $added = array_map($label, array_values(array_diff($after, $before)));
        $removed = array_map($label, array_values(array_diff($before, $after)));
        $parts = [];

        if ($added !== []) {
            $parts[] = 'Passou a ter: '.implode('; ', $added).'.';
        }

        if ($removed !== []) {
            $parts[] = 'Deixou de ter: '.implode('; ', $removed).'.';
        }

        return $parts === [] ? 'Dados do perfil alterados (permissões iguais).' : implode(' ', $parts);
    }

    public static function record(string $action, ?string $subject, string $summary): void
    {
        AccessAudit::query()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'subject' => $subject !== null ? mb_substr($subject, 0, 160) : null,
            'summary' => mb_substr($summary, 0, 1000),
        ]);
    }
}

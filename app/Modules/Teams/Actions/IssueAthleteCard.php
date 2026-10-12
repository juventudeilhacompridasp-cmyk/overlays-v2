<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Support\Facades\DB;

/** Emite (ou renova) o código da carteirinha de um integrante do elenco; o código vai no QR e abre a página pública de verificação. */
class IssueAthleteCard
{
    /** Sem 0/O/1/I/L para não confundir quando digitado. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function handle(RosterEntry $rosterEntry, bool $regenerate = false): string
    {
        if (! $regenerate && is_string($rosterEntry->card_code) && $rosterEntry->card_code !== '') {
            return $rosterEntry->card_code;
        }

        return DB::transaction(function () use ($rosterEntry): string {
            do {
                $code = self::newCode();
            } while (RosterEntry::query()->withoutGlobalScopes()->where('card_code', $code)->exists());

            $rosterEntry->update(['card_code' => $code]);

            return $code;
        });
    }

    public static function newCode(): string
    {
        $code = '';

        for ($i = 0; $i < 10; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}

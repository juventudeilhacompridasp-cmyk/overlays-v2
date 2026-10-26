<?php

namespace App\Modules\Teams\Support;

use Illuminate\Support\Carbon;

/**
 * Situação de um documento com validade (puro, sem banco): válido, vencendo (dentro da janela de aviso) ou vencido.
 * O documento vale até o fim do dia da validade.
 */
final class DocumentValidity
{
    public const VALID = 'valid';

    public const EXPIRING = 'expiring';

    public const EXPIRED = 'expired';

    public static function status(Carbon $expiresOn, Carbon $today, int $warnDays): string
    {
        $days = self::daysLeft($expiresOn, $today);

        if ($days < 0) {
            return self::EXPIRED;
        }

        return $days <= $warnDays ? self::EXPIRING : self::VALID;
    }

    /** Dias que faltam (0 = vence hoje; negativo = vencido há tantos dias). */
    public static function daysLeft(Carbon $expiresOn, Carbon $today): int
    {
        return (int) $today->copy()->startOfDay()->diffInDays($expiresOn->copy()->startOfDay(), false);
    }

    /** Vale na data do jogo (ou outra)? */
    public static function validOn(Carbon $expiresOn, Carbon $date): bool
    {
        return $expiresOn->copy()->startOfDay()->gte($date->copy()->startOfDay());
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::EXPIRED => 'Vencido',
            self::EXPIRING => 'Vence em breve',
            default => 'Válido',
        };
    }
}

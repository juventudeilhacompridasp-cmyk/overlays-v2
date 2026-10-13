<?php

namespace App\Modules\Tenancy\Enums;

enum LegalType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';

    public function label(): string
    {
        return match ($this) {
            self::Terms => 'Termos de uso',
            self::Privacy => 'Política de privacidade',
        };
    }

    /** Endereço público da página. */
    public function path(): string
    {
        return match ($this) {
            self::Terms => '/termos',
            self::Privacy => '/privacidade',
        };
    }
}

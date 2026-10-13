<?php

namespace App\Modules\Tenancy\Enums;

/** Pacotes: só definem quais recursos começam ligados. A liberação é manual (cobrança automática fica para depois). */
enum Plan: string
{
    case Basic = 'basic';
    case Standard = 'standard';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Básico',
            self::Standard => 'Padrão',
            self::Pro => 'Completo',
        };
    }

    /**
     * @return list<Feature>
     */
    public function features(): array
    {
        return match ($this) {
            self::Basic => [],
            self::Standard => [Feature::OnlineRegistration, Feature::AthleteCards, Feature::Reports, Feature::Embeds],
            self::Pro => Feature::cases(),
        };
    }
}

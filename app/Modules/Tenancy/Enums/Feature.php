<?php

namespace App\Modules\Tenancy\Enums;

/** Recursos que a plataforma libera por organização (o núcleo — competições, súmula, portal — vale para todas). */
enum Feature: string
{
    case OnlineRegistration = 'online_registration';
    case AthleteCards = 'athlete_cards';
    case Reports = 'reports';
    case Overlays = 'overlays';
    case CustomDomain = 'custom_domain';
    case WhiteLabel = 'white_label';

    public function label(): string
    {
        return match ($this) {
            self::OnlineRegistration => 'Inscrição online',
            self::AthleteCards => 'Carteirinha com QR',
            self::Reports => 'Relatórios e CSV',
            self::Overlays => 'Overlays para OBS',
            self::CustomDomain => 'Domínio próprio',
            self::WhiteLabel => 'Marca própria (white-label)',
        };
    }
}

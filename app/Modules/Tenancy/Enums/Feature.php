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
    case Embeds = 'embeds';
    case Push = 'push';
    case TeamPortal = 'team_portal';
    case SheetImport = 'sheet_import';

    public function label(): string
    {
        return match ($this) {
            self::OnlineRegistration => 'Inscrição online',
            self::AthleteCards => 'Carteirinha com QR',
            self::Reports => 'Relatórios e CSV',
            self::Overlays => 'Overlays para OBS',
            self::CustomDomain => 'Domínio próprio',
            self::WhiteLabel => 'Marca própria (white-label)',
            self::Embeds => 'Widgets para outros sites',
            self::Push => 'Avisos push (jogos e equipes)',
            self::TeamPortal => 'Área do gestor de equipe (escalação e esquema tático)',
            self::SheetImport => 'Súmulas enviadas depois do jogo (leitura e conferência)',
        };
    }
}

<?php

namespace App\Modules\Competitions\Enums;

enum StageType: string
{
    case League = 'league';
    case Groups = 'groups';
    case Knockout = 'knockout';

    public function label(): string
    {
        return match ($this) {
            self::League => 'Pontos corridos',
            self::Groups => 'Fase de grupos',
            self::Knockout => 'Mata-mata',
        };
    }
}

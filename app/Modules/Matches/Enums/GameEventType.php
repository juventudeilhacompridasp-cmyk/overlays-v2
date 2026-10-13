<?php

namespace App\Modules\Matches\Enums;

enum GameEventType: string
{
    case Goal = 'goal';
    case OwnGoal = 'own_goal';
    case YellowCard = 'yellow_card';
    case RedCard = 'red_card';
    case BlueCard = 'blue_card';
    case Substitution = 'substitution';
    case PeriodStart = 'period_start';
    case PeriodEnd = 'period_end';
    case PenaltyKick = 'penalty_kick';
    case Warning = 'warning';
    case Foul = 'foul';
    /** Ponto de rally (esportes de sets); o período é o número do set. */
    case Point = 'point';

    public function label(): string
    {
        return match ($this) {
            self::Goal => 'Gol',
            self::OwnGoal => 'Gol contra',
            self::YellowCard => 'Cartão amarelo',
            self::RedCard => 'Cartão vermelho',
            self::BlueCard => 'Cartão azul',
            self::Substitution => 'Substituição',
            self::PeriodStart => 'Início de período',
            self::PeriodEnd => 'Fim de período',
            self::PenaltyKick => 'Cobrança de pênalti',
            self::Warning => 'Advertência',
            self::Foul => 'Falta',
            self::Point => 'Ponto',
        };
    }
}

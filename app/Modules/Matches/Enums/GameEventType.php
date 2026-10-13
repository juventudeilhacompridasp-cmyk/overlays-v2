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
    /** Basquete: lance livre (1), cesta de quadra (2) e cesta de três (3). */
    case FreeThrow = 'free_throw';
    case FieldGoal = 'field_goal';
    case ThreePointer = 'three_pointer';
    /** Basquete: o relógio para / volta dentro do período. */
    case ClockPause = 'clock_pause';
    case ClockResume = 'clock_resume';
    /** Basquete: pedido de tempo da equipe (sem atleta). */
    case Timeout = 'timeout';

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
            self::FreeThrow => 'Lance livre (1)',
            self::FieldGoal => 'Cesta de 2',
            self::ThreePointer => 'Cesta de 3',
            self::ClockPause => 'Relógio parado',
            self::ClockResume => 'Relógio em andamento',
            self::Timeout => 'Pedido de tempo',
        };
    }

    /** Pontos que o lance vale (esportes de pontos); null se o evento não pontua. */
    public function pointValue(): ?int
    {
        return match ($this) {
            self::FreeThrow => 1,
            self::FieldGoal => 2,
            self::ThreePointer => 3,
            default => null,
        };
    }

    /** Eventos que só movem o relógio (não passam pela súmula). */
    public function isClockMark(): bool
    {
        return in_array($this, [self::PeriodStart, self::PeriodEnd, self::ClockPause, self::ClockResume], true);
    }

    /**
     * @return list<self>
     */
    public static function clockMarks(): array
    {
        return [self::PeriodStart, self::PeriodEnd, self::ClockPause, self::ClockResume];
    }

    /**
     * @return list<self>
     */
    public static function scoring(): array
    {
        return [self::FreeThrow, self::FieldGoal, self::ThreePointer];
    }
}

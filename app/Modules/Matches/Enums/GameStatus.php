<?php

namespace App\Modules\Matches\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Postponed = 'postponed';
    case Suspended = 'suspended';
    case Walkover = 'walkover';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::InProgress => 'Em andamento',
            self::Finished => 'Encerrado',
            self::Postponed => 'Adiado',
            self::Suspended => 'Suspenso',
            self::Walkover => 'W.O.',
            self::Cancelled => 'Cancelado',
        };
    }

    /** Jogo já decidido (conta na classificação). */
    public function isDecided(): bool
    {
        return $this === self::Finished || $this === self::Walkover;
    }
}

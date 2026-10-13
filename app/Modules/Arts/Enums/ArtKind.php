<?php

namespace App\Modules\Arts\Enums;

/** Tipos de arte de uma partida (ADR 0009). */
enum ArtKind: string
{
    case Preview = 'preview';
    case Result = 'result';
    case Lineup = 'lineup';

    public function label(): string
    {
        return match ($this) {
            self::Preview => 'Jogo do dia',
            self::Result => 'Resultado',
            self::Lineup => 'Escalação',
        };
    }
}

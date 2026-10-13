<?php

namespace App\Modules\Arts\Enums;

/** Tipos de arte de uma partida (ADR 0009). */
enum ArtKind: string
{
    case Preview = 'preview';
    case Result = 'result';
    case Lineup = 'lineup';
    case Round = 'round';
    case Cover = 'cover';
    case Standings = 'standings';
    case Scorers = 'scorers';

    public function label(): string
    {
        return match ($this) {
            self::Preview => 'Jogo do dia',
            self::Result => 'Resultado',
            self::Lineup => 'Escalação',
            self::Round => 'Rodada / dia',
            self::Cover => 'Capa / anúncio',
            self::Standings => 'Classificação',
            self::Scorers => 'Artilharia',
        };
    }
}

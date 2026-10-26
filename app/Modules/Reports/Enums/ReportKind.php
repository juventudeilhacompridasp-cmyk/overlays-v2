<?php

namespace App\Modules\Reports\Enums;

/** Assunto do relatório: uma partida ou uma divisão de uma edição (o "campeonato"). */
enum ReportKind: string
{
    case Game = 'game';
    case Championship = 'championship';

    public function label(): string
    {
        return match ($this) {
            self::Game => 'Relatório da partida',
            self::Championship => 'Relatório do campeonato',
        };
    }
}

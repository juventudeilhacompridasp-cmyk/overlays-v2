<?php

namespace App\Modules\Market\Enums;

/** Situação de uma denúncia do Mercado (ADR 0008, parte 5). */
enum ReportStatus: string
{
    case Open = 'open';
    case Actioned = 'actioned';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::Actioned => 'Procedente',
            self::Dismissed => 'Arquivada',
        };
    }
}

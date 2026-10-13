<?php

namespace App\Modules\Matches\Enums;

/** Situação de uma súmula enviada depois do jogo (ADR 0004). */
enum SheetUploadStatus: string
{
    case Uploaded = 'uploaded';
    case Reading = 'reading';
    case Read = 'read';
    case Reviewed = 'reviewed';
    case Failed = 'failed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Enviada',
            self::Reading => 'Lendo',
            self::Read => 'Aguardando revisão',
            self::Reviewed => 'Conferida',
            self::Failed => 'Falhou',
            self::Discarded => 'Descartada',
        };
    }
}

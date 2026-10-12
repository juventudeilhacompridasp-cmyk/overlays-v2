<?php

namespace App\Modules\Registration\Enums;

enum RegistrationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Em preenchimento',
            self::Submitted => 'Em análise',
            self::Returned => 'Devolvida para ajustes',
            self::Approved => 'Aprovada',
            self::Rejected => 'Recusada',
        };
    }

    /** A equipe ainda pode alterar elenco, dados e documentos. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }
}

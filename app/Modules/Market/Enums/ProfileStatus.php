<?php

namespace App\Modules\Market\Enums;

/** Situação do perfil no Mercado (ADR 0008). Só `published` aparece para os gestores. */
enum ProfileStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Signed = 'signed';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Published => 'Publicado',
            self::Paused => 'Pausado',
            self::Signed => 'Contratado',
            self::Suspended => 'Suspenso',
        };
    }
}

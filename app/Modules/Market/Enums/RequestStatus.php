<?php

namespace App\Modules\Market\Enums;

/** Situação de um pedido de contato (ADR 0008). */
enum RequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando resposta',
            self::Accepted => 'Aceito',
            self::Declined => 'Recusado',
            self::Withdrawn => 'Cancelado',
            self::Expired => 'Expirado',
        };
    }
}

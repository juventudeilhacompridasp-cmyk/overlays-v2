<?php

namespace App\Modules\Status\Enums;

/** Efeito de um incidente sobre os componentes afetados. */
enum Impact: string
{
    case None = 'none';
    case Minor = 'minor';
    case Major = 'major';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Sem impacto',
            self::Minor => 'Instabilidade',
            self::Major => 'Fora do ar',
            self::Maintenance => 'Manutenção',
        };
    }

    /** Situação do componente enquanto o incidente está aberto. */
    public function componentStatus(): string
    {
        return match ($this) {
            self::None => 'operational',
            self::Minor => 'degraded',
            self::Major => 'outage',
            self::Maintenance => 'maintenance',
        };
    }
}

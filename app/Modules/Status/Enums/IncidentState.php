<?php

namespace App\Modules\Status\Enums;

/** Andamento de um incidente (ou de uma manutenção programada). */
enum IncidentState: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Investigating => 'Investigando',
            self::Identified => 'Causa identificada',
            self::Monitoring => 'Monitorando',
            self::Resolved => 'Resolvido',
            self::Scheduled => 'Agendada',
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluída',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Resolved || $this === self::Completed;
    }

    /** @return list<self> */
    public static function forIncident(): array
    {
        return [self::Investigating, self::Identified, self::Monitoring, self::Resolved];
    }

    /** @return list<self> */
    public static function forMaintenance(): array
    {
        return [self::Scheduled, self::InProgress, self::Completed];
    }
}

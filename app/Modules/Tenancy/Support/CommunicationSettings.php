<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

/**
 * Comunicações da organização (`organizations.settings.communications`): quais e-mails/avisos saem e quando.
 * O padrão é o comportamento original (tudo ligado); o valor salvo sobrescreve. Quem dispara a comunicação consulta aqui.
 * O pacote continua mandando: desligar aqui só reduz, nunca libera um recurso que a organização não tem.
 */
final class CommunicationSettings
{
    /** Interruptores (chave => padrão). */
    public const SWITCHES = [
        'registration_opened' => true,
        'registration_approved' => true,
        'registration_returned' => true,
        'registration_rejected' => true,
        'manager_invitation' => true,
        'manager_lineup_reminder' => true,
        'manager_game_changed' => true,
        'manager_suspensions' => true,
        'push_started' => true,
        'push_score' => true,
        'push_finished' => true,
        'market_contact_request' => true,
        'market_contact_answered' => true,
        'market_profile_expiring' => true,
        'sheet_read_finished' => true,
    ];

    /** Minutos antes do prazo do gestor em que sai o primeiro e o segundo lembrete de escalação. */
    public const FIRST_REMINDER_MINUTES = 1440;

    public const SECOND_REMINDER_MINUTES = 120;

    /**
     * @return array<string, bool|int>
     */
    public static function all(?Organization $organization = null): array
    {
        $organization ??= CurrentOrganization::get();
        $saved = $organization?->settings['communications'] ?? [];
        $saved = is_array($saved) ? $saved : [];

        $settings = [];

        foreach (self::SWITCHES as $key => $default) {
            $settings[$key] = array_key_exists($key, $saved) ? (bool) $saved[$key] : $default;
        }

        $first = self::minutes($saved['reminder_first_minutes'] ?? null, self::FIRST_REMINDER_MINUTES);
        $second = self::minutes($saved['reminder_second_minutes'] ?? null, self::SECOND_REMINDER_MINUTES);

        // O segundo lembrete é sempre o mais próximo do prazo.
        $settings['reminder_first_minutes'] = max($first, $second + 1);
        $settings['reminder_second_minutes'] = $second;

        return $settings;
    }

    public static function enabled(string $key, ?Organization $organization = null): bool
    {
        return (bool) (self::all($organization)[$key] ?? false);
    }

    /** @return array{0: int, 1: int} [primeiro, segundo] lembrete, em minutos antes do prazo */
    public static function reminderMinutes(?Organization $organization = null): array
    {
        $all = self::all($organization);

        return [(int) $all['reminder_first_minutes'], (int) $all['reminder_second_minutes']];
    }

    private static function minutes(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}

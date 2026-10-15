<?php

namespace App\Modules\Help\Support;

use App\Models\User;
use App\Modules\Help\Models\HelpQuestion;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;

/**
 * Respostas com IA na central de ajuda (ADR 0006, parte 3). Só funcionam com TRÊS condições: o recurso `help_ai` (sob
 * demanda) liberado pela plataforma, o interruptor da organização ligado (`settings.help_ai.enabled`, padrão desligado) e
 * a chave do provedor configurada. Os limites de custo são da plataforma.
 */
final class HelpAiSettings
{
    public static function released(): bool
    {
        return CurrentOrganization::has(Feature::HelpAi);
    }

    public static function enabled(): bool
    {
        return (bool) (CurrentOrganization::get()?->settings['help_ai']['enabled'] ?? false);
    }

    public static function configured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public static function available(): bool
    {
        return self::released() && self::enabled() && self::configured();
    }

    public static function userDailyLimit(): int
    {
        return max(1, (int) config('platform.help_ai.user_daily'));
    }

    public static function monthlyLimit(): int
    {
        return max(1, (int) config('platform.help_ai.monthly'));
    }

    public static function usedToday(User $user): int
    {
        return HelpQuestion::query()->where('user_id', $user->getKey())->where('charged', true)->where('created_at', '>=', now()->startOfDay())->count();
    }

    public static function usedThisMonth(): int
    {
        return HelpQuestion::query()->where('charged', true)->where('created_at', '>=', now()->startOfMonth())->count();
    }

    /** Perguntas que a pessoa ainda pode fazer hoje (0 = estourou o dia ou o mês da organização). */
    public static function remaining(User $user): int
    {
        return max(0, min(self::userDailyLimit() - self::usedToday($user), self::monthlyLimit() - self::usedThisMonth()));
    }
}

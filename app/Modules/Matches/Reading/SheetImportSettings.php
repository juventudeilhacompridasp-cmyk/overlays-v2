<?php

namespace App\Modules\Matches\Reading;

use App\Modules\Matches\Models\GameSheetRead;
use App\Modules\Tenancy\CurrentOrganization;

/**
 * Parâmetros da leitura de súmulas da organização atual (`settings.sheet_import`) e limites de custo da plataforma.
 * A leitura por IA só vale se a organização não a desligou, a plataforma tem chave e os limites não estouraram.
 */
final class SheetImportSettings
{
    public static function aiEnabled(): bool
    {
        return (bool) (CurrentOrganization::get()?->settings['sheet_import']['ai_enabled'] ?? true);
    }

    public static function aiAvailable(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public static function ceiling(): int
    {
        return max(1, (int) config('platform.sheet_import.max_reads_per_game_ceiling'));
    }

    /** Leituras por IA por jogo: o valor da organização, limitado pelo teto da plataforma. */
    public static function maxReadsPerGame(): int
    {
        $own = CurrentOrganization::get()?->settings['sheet_import']['max_reads_per_game'] ?? null;
        $value = is_numeric($own) ? (int) $own : (int) config('platform.sheet_import.max_reads_per_game');

        return max(1, min($value, self::ceiling()));
    }

    public static function monthlyLimit(): int
    {
        return max(0, (int) config('platform.sheet_import.monthly_reads'));
    }

    public static function usedForGame(string $gameId): int
    {
        return GameSheetRead::query()->where('game_id', $gameId)->where('reader', 'vision')->count();
    }

    public static function usedThisMonth(): int
    {
        return GameSheetRead::query()->where('reader', 'vision')->where('created_at', '>=', now()->startOfMonth())->count();
    }

    /**
     * @return array{ai_enabled: bool, max_reads_per_game: int}
     */
    public static function all(): array
    {
        return ['ai_enabled' => self::aiEnabled(), 'max_reads_per_game' => self::maxReadsPerGame()];
    }
}

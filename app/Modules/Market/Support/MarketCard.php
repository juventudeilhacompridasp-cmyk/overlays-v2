<?php

namespace App\Modules\Market\Support;

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Market\Models\MarketProfile;

/**
 * Cartão do atleta mostrado ao gestor (ADR 0008): sem contato, sem data de nascimento exata (só a faixa de idade), sem
 * documentos. O nome é o apelido ou, na falta dele, o nome — maiores de 18 anos, por isso não há abreviação de menor.
 */
final class MarketCard
{
    /**
     * @return array<string, mixed>
     */
    public static function of(MarketProfile $profile): array
    {
        $sport = $profile->sport !== null ? Sport::tryFrom($profile->sport) : null;
        $positions = $sport?->profile()->positions ?? [];

        return [
            'id' => $profile->getKey(),
            'name' => filled($profile->display_name) ? $profile->display_name : $profile->user->name,
            'photo_url' => $profile->photoUrl(96),
            'sport' => $sport?->label(),
            'positions' => array_values(array_filter(array_map(fn (string $key) => $positions[$key] ?? null, $profile->positions ?? []))),
            'age_range' => self::ageRange($profile->age()),
            'dominant_foot' => MarketOptions::FEET[$profile->dominant_foot ?? ''] ?? null,
            'height_cm' => $profile->height_cm,
            'level' => MarketOptions::LEVELS[$profile->level ?? ''] ?? null,
            'bio' => $profile->bio,
            'city' => $profile->city,
            'neighborhood' => $profile->neighborhood,
            'availability' => self::availability($profile->availability ?? []),
            'published_at' => $profile->published_at?->format('d/m/Y'),
        ];
    }

    /** Faixa de 5 anos ("25 a 29 anos"), nunca a idade exata. */
    public static function ageRange(int $age): string
    {
        $start = intdiv($age, 5) * 5;

        // Nunca sugere menor de 18 (a primeira faixa é "18 a 19 anos").
        return max($start, MarketOptions::MIN_AGE).' a '.($start + 4).' anos';
    }

    /**
     * @param  array<string, list<string>>  $availability
     * @return list<array{day: string, periods: list<string>}>
     */
    private static function availability(array $availability): array
    {
        $rows = [];

        foreach (MarketOptions::DAYS as $key => $label) {
            $periods = array_values(array_filter(array_map(fn (string $period) => MarketOptions::PERIODS[$period] ?? null, $availability[$key] ?? [])));

            if ($periods !== []) {
                $rows[] = ['day' => $label, 'periods' => $periods];
            }
        }

        return $rows;
    }
}

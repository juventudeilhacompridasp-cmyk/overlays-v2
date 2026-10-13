<?php

namespace App\Modules\Market\Support;

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Models\MarketProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Busca do gestor no Mercado (ADR 0008): só perfis PUBLICADOS e ainda dentro do prazo de revalidação, da organização
 * atual (escopo global). Os filtros são todos opcionais; o que não é conhecido é ignorado.
 */
final class MarketSearch
{
    /** Filtros aceitos, já saneados. @return array<string, string|int|null> */
    public static function filters(array $input): array
    {
        $text = fn (string $key): ?string => isset($input[$key]) && is_string($input[$key]) && trim($input[$key]) !== '' ? Str::limit(trim($input[$key]), 80, '') : null;
        $choice = fn (string $key, array $allowed): ?string => isset($input[$key]) && is_string($input[$key]) && in_array($input[$key], $allowed, true) ? $input[$key] : null;
        $age = fn (string $key): ?int => isset($input[$key]) && is_numeric($input[$key]) && $input[$key] >= MarketOptions::MIN_AGE && $input[$key] <= 80 ? (int) $input[$key] : null;

        return [
            'sport' => $choice('sport', ['football', 'futsal', 'volleyball', 'basketball']),
            'position' => $text('position'),
            'city' => $text('city'),
            'neighborhood' => $text('neighborhood'),
            'day' => $choice('day', array_keys(MarketOptions::DAYS)),
            'period' => $choice('period', array_keys(MarketOptions::PERIODS)),
            'level' => $choice('level', array_keys(MarketOptions::LEVELS)),
            'age_min' => $age('age_min'),
            'age_max' => $age('age_max'),
        ];
    }

    /**
     * @param  array<string, string|int|null>  $filters  saída de {@see filters()}
     * @return Builder<MarketProfile>
     */
    public static function query(array $filters): Builder
    {
        $query = MarketProfile::query()
            ->with('user:id,name')
            ->where('status', ProfileStatus::Published->value)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        if ($filters['sport'] ?? null) {
            $query->where('sport', $filters['sport']);
        }

        if ($filters['position'] ?? null) {
            $query->whereRaw('cast(positions as text) like ?', ['%"'.str_replace(['%', '_', '"'], '', (string) $filters['position']).'"%']);
        }

        foreach (['city', 'neighborhood'] as $column) {
            if ($filters[$column] ?? null) {
                $query->whereRaw("lower({$column}) like ?", ['%'.mb_strtolower(str_replace(['%', '_'], '', (string) $filters[$column])).'%']);
            }
        }

        if ($filters['level'] ?? null) {
            $query->where('level', $filters['level']);
        }

        // Disponibilidade: dia (e período, se informado) — o JSON guarda {"sat": ["morning", ...]}.
        if ($filters['day'] ?? null) {
            // json (Postgres) não aceita LIKE: compara o texto serializado.
            $query->whereRaw('cast(availability as text) like ?', ['%"'.$filters['day'].'":%']);
        }

        if ($filters['period'] ?? null) {
            $query->whereRaw('cast(availability as text) like ?', ['%"'.$filters['period'].'"%']);
        }

        if ($filters['age_min'] ?? null) {
            $query->whereDate('birth_date', '<=', now()->subYears((int) $filters['age_min'])->toDateString());
        }

        if ($filters['age_max'] ?? null) {
            $query->whereDate('birth_date', '>', now()->subYears((int) $filters['age_max'] + 1)->toDateString());
        }

        return $query->orderByDesc('published_at')->orderBy('id');
    }
}

<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Plan;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $settings
 * @property array<int, string>|null $features
 * @property array<string, int|null>|null $limits
 */
#[Unguarded]
#[UseFactory(OrganizationFactory::class)]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'features' => 'array',
            'limits' => 'array',
        ];
    }

    public function plan(): Plan
    {
        return Plan::tryFrom((string) $this->getAttribute('plan')) ?? Plan::Basic;
    }

    /**
     * Recursos ligados: a lista própria da organização (liberação manual) ou, sem ela, os do pacote.
     *
     * @return list<string>
     */
    public function featureKeys(): array
    {
        if ($this->features !== null) {
            return array_values(array_intersect($this->features, array_map(fn (Feature $feature) => $feature->value, Feature::cases())));
        }

        return array_map(fn (Feature $feature) => $feature->value, $this->plan()->features());
    }

    public function hasFeature(Feature $feature): bool
    {
        return in_array($feature->value, $this->featureKeys(), true);
    }

    /** Limite numérico da organização (null = sem limite). */
    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}

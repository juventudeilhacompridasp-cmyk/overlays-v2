<?php

namespace App\Modules\Competitions\Models;

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Sport $sport
 */
#[Unguarded]
#[UseFactory(CompetitionFactory::class)]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'sport' => Sport::class,
        ];
    }

    /**
     * @return HasMany<Season, $this>
     */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }
}

<?php

namespace App\Modules\Competitions\Models;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\StageFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property StageType $type
 * @property array<string, mixed>|null $settings
 */
#[Unguarded]
#[UseFactory(StageFactory::class)]
class Stage extends Model
{
    /** @use HasFactory<StageFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => StageType::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}

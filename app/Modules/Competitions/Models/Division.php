<?php

namespace App\Modules\Competitions\Models;

use App\Modules\Competitions\Enums\Gender;
use App\Modules\Matches\Models\Game;
use App\Modules\Rules\RuleSet;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
#[UseFactory(DivisionFactory::class)]
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'rules' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return HasMany<Stage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /** Regulamento efetivo: regras da edição com as sobrescritas desta divisão. */
    public function ruleSet(): RuleSet
    {
        $rules = array_replace_recursive($this->season->rules ?? [], $this->rules ?? []);

        foreach (['tiebreakers'] as $listKey) {
            if (isset($this->rules[$listKey])) {
                $rules[$listKey] = $this->rules[$listKey];
            }
        }

        return RuleSet::fromArray($rules);
    }
}

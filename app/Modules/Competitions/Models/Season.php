<?php

namespace App\Modules\Competitions\Models;

use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Rules\RuleSet;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property SeasonStatus $status
 * @property array<string, mixed>|null $rules
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 */
#[Unguarded]
#[UseFactory(SeasonFactory::class)]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => SeasonStatus::class,
            'rules' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Edições visíveis no portal público: rascunho nunca aparece.
     *
     * @param  Builder<Season>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', '!=', SeasonStatus::Draft->value);
    }

    public function isPublished(): bool
    {
        return $this->status !== SeasonStatus::Draft;
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return HasMany<Division, $this>
     */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    /** Regulamento da edição (padrões do regulamento de referência para o que não foi configurado). */
    public function ruleSet(): RuleSet
    {
        /** @var array<string, mixed> $rules */
        $rules = $this->rules ?? [];

        return RuleSet::fromArray($rules);
    }
}

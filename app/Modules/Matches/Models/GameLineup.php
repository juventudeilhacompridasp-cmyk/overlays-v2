<?php

namespace App\Modules\Matches\Models;

use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\GameLineupFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property LineupSlot $slot
 */
#[Unguarded]
#[UseFactory(GameLineupFactory::class)]
class GameLineup extends Model
{
    /** @use HasFactory<GameLineupFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $table = 'game_lineups';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'slot' => LineupSlot::class,
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}

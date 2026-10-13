<?php

namespace App\Modules\Matches\Models;

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Rules\Sheet\GameClock;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property GameStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property list<array{home: int, away: int, winner: string|null, finished: bool}>|null $sets
 */
#[Unguarded]
#[UseFactory(GameFactory::class)]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /** Mesmos padrões do banco, para o modelo recém-criado já refletir a situação inicial. */
    protected $attributes = [
        'status' => 'scheduled',
        'home_score' => 0,
        'away_score' => 0,
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'sets' => 'array',
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
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function homeEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'home_entry_id');
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function awayEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'away_entry_id');
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return HasMany<GameOfficial, $this>
     */
    public function officials(): HasMany
    {
        return $this->hasMany(GameOfficial::class);
    }

    /**
     * @return HasMany<GameEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    /**
     * @return HasMany<GameLineup, $this>
     */
    public function lineups(): HasMany
    {
        return $this->hasMany(GameLineup::class);
    }

    /** Esporte do jogo (o da competição). */
    public function sport(): Sport
    {
        return $this->division->season->competition->sport;
    }

    /** Cronômetro do jogo, com a duração dos períodos do esporte. */
    public function clock(): GameClock
    {
        return $this->sport()->profile()->clock();
    }
}

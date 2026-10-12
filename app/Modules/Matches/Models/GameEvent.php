<?php

namespace App\Modules\Matches\Models;

use App\Models\User;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property GameEventType $type
 * @property array<string, mixed>|null $payload
 * @property Carbon $created_at
 */
#[Unguarded]
#[UseFactory(GameEventFactory::class)]
class GameEvent extends Model
{
    /** @use HasFactory<GameEventFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'game_events';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => GameEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Eventos de jogo são somente de acréscimo: para corrigir, registre um evento que compensa o anterior.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Eventos de jogo são imutáveis; registre um evento compensatório.');
        });

        static::deleting(function (): never {
            throw new LogicException('Eventos de jogo não podem ser apagados; registre um evento compensatório.');
        });
    }

    /**
     * Eventos que valem: exclui os que foram anulados por um evento compensatório e os próprios
     * eventos compensatórios (que só servem para anular o evento original).
     *
     * @param  Builder<GameEvent>  $query
     */
    public function scopeEffective(Builder $query): void
    {
        $query->whereNull('compensates_event_id')
            ->whereNotIn('id', fn ($sub) => $sub->select('compensates_event_id')
                ->from('game_events')
                ->whereNotNull('compensates_event_id'));
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function secondaryPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'secondary_person_id');
    }

    /**
     * @return BelongsTo<GameEvent, $this>
     */
    public function compensates(): BelongsTo
    {
        return $this->belongsTo(GameEvent::class, 'compensates_event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

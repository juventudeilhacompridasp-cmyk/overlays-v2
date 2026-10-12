<?php

namespace Database\Factories;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameEvent>
 */
class GameEventFactory extends Factory
{
    protected $model = GameEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'organization_id' => fn (array $attributes) => Game::withoutGlobalScopes()->findOrFail($attributes['game_id'])->organization_id,
            'type' => GameEventType::Goal,
            'period' => 1,
            'minute' => fake()->numberBetween(1, 45),
            'entry_id' => fn (array $attributes) => Game::withoutGlobalScopes()->findOrFail($attributes['game_id'])->home_entry_id,
            'person_id' => null,
            'secondary_person_id' => null,
            'payload' => null,
            'compensates_event_id' => null,
            'recorded_by' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stage_id' => Stage::factory(),
            'division_id' => fn (array $attributes) => Stage::withoutGlobalScopes()->findOrFail($attributes['stage_id'])->division_id,
            'organization_id' => fn (array $attributes) => Stage::withoutGlobalScopes()->findOrFail($attributes['stage_id'])->organization_id,
            'group_id' => null,
            'round' => 1,
            'number' => null,
            'home_entry_id' => fn (array $attributes) => Entry::factory()->create(['division_id' => $attributes['division_id']])->id,
            'away_entry_id' => fn (array $attributes) => Entry::factory()->create(['division_id' => $attributes['division_id']])->id,
            'venue_id' => null,
            'scheduled_at' => null,
            'status' => GameStatus::Scheduled,
            'home_score' => 0,
            'away_score' => 0,
        ];
    }
}

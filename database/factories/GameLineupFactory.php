<?php

namespace Database\Factories;

use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Teams\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameLineup>
 */
class GameLineupFactory extends Factory
{
    protected $model = GameLineup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'organization_id' => fn (array $attributes) => Game::withoutGlobalScopes()->findOrFail($attributes['game_id'])->organization_id,
            'entry_id' => fn (array $attributes) => Game::withoutGlobalScopes()->findOrFail($attributes['game_id'])->home_entry_id,
            'person_id' => fn (array $attributes) => Person::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'slot' => LineupSlot::Starter,
            'shirt_number' => null,
        ];
    }
}

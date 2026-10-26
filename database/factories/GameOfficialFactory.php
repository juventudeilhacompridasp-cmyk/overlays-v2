<?php

namespace Database\Factories;

use App\Modules\Matches\Enums\OfficialRole;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameOfficial;
use App\Modules\Matches\Models\Official;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameOfficial>
 */
class GameOfficialFactory extends Factory
{
    protected $model = GameOfficial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'organization_id' => fn (array $attributes) => Game::withoutGlobalScopes()->findOrFail($attributes['game_id'])->organization_id,
            'official_id' => fn (array $attributes) => Official::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'role' => OfficialRole::Referee,
        ];
    }
}

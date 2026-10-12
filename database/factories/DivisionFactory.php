<?php

namespace Database\Factories;

use App\Modules\Competitions\Enums\Gender;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'organization_id' => fn (array $attributes) => Season::withoutGlobalScopes()->findOrFail($attributes['season_id'])->organization_id,
            'name' => 'SUB-'.fake()->unique()->numberBetween(5, 99),
            'category' => null,
            'gender' => Gender::Male,
            'rules' => null,
        ];
    }
}

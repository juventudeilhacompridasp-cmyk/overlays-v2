<?php

namespace Database\Factories;

use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'organization_id' => fn (array $attributes) => Competition::withoutGlobalScopes()->findOrFail($attributes['competition_id'])->organization_id,
            'name' => (string) ($year = fake()->numberBetween(2024, 2030)),
            'year' => $year,
            'starts_on' => null,
            'ends_on' => null,
            'status' => SeasonStatus::Draft,
            'rules' => null,
        ];
    }
}

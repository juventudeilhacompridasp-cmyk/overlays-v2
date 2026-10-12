<?php

namespace Database\Factories;

use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn () => CurrentOrganization::id() ?? Organization::factory()->create()->id,
            'name' => fake()->unique()->city().' FC',
            'short_name' => null,
            'city' => fake()->city(),
            'logo_path' => null,
            'primary_color' => null,
        ];
    }
}

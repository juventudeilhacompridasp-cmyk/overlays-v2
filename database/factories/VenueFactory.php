<?php

namespace Database\Factories;

use App\Modules\Matches\Models\Venue;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn () => CurrentOrganization::id() ?? Organization::factory()->create()->id,
            'name' => 'Estádio '.fake()->unique()->lastName(),
            'city' => fake()->city(),
            'address' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Modules\Matches\Models\Venue;
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
            'organization_id' => Organization::factory(),
            'name' => 'Estádio '.fake()->unique()->lastName(),
            'city' => fake()->city(),
            'address' => null,
        ];
    }
}

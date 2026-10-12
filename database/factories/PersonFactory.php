<?php

namespace Database\Factories;

use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'nickname' => null,
            'birth_date' => fake()->dateTimeBetween('-40 years', '-10 years')->format('Y-m-d'),
            'document_hash' => null,
            'photo_path' => null,
        ];
    }
}

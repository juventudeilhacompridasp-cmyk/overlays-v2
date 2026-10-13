<?php

namespace Database\Factories;

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $name = fake()->unique()->company(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'settings' => null,
            'plan' => 'pro',
        ];
    }
}

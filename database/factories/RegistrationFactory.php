<?php

namespace Database\Factories;

use App\Modules\Competitions\Models\Division;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'organization_id' => fn (array $attributes) => Division::withoutGlobalScopes()->findOrFail($attributes['division_id'])->organization_id,
            'season_id' => fn (array $attributes) => Division::withoutGlobalScopes()->findOrFail($attributes['division_id'])->season_id,
            'token' => Registration::newToken(),
            'status' => RegistrationStatus::Draft,
            'team_name' => fake()->unique()->company(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
        ];
    }
}

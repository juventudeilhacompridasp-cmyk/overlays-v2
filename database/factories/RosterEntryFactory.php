<?php

namespace Database\Factories;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterEntry>
 */
class RosterEntryFactory extends Factory
{
    protected $model = RosterEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'organization_id' => fn (array $attributes) => Entry::withoutGlobalScopes()->findOrFail($attributes['entry_id'])->organization_id,
            'person_id' => fn (array $attributes) => Person::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'role' => RosterRole::Athlete,
            'shirt_number' => fake()->numberBetween(1, 99),
            'status' => RosterStatus::Active,
        ];
    }
}

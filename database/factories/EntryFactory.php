<?php

namespace Database\Factories;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    protected $model = Entry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'organization_id' => fn (array $attributes) => Division::withoutGlobalScopes()->findOrFail($attributes['division_id'])->organization_id,
            'team_id' => fn (array $attributes) => Team::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'seed' => null,
            'status' => EntryStatus::Confirmed,
        ];
    }
}

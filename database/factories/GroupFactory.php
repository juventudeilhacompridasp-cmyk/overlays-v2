<?php

namespace Database\Factories;

use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stage_id' => Stage::factory(),
            'organization_id' => fn (array $attributes) => Stage::withoutGlobalScopes()->findOrFail($attributes['stage_id'])->organization_id,
            'name' => 'Grupo '.fake()->unique()->bothify('??##'),
            'sequence' => 1,
        ];
    }
}

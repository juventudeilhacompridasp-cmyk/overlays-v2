<?php

namespace Database\Factories;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stage>
 */
class StageFactory extends Factory
{
    protected $model = Stage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'organization_id' => fn (array $attributes) => Division::withoutGlobalScopes()->findOrFail($attributes['division_id'])->organization_id,
            'name' => 'Fase '.($sequence = fake()->unique()->numberBetween(1, 9999)),
            'type' => StageType::League,
            'sequence' => $sequence,
            'settings' => null,
        ];
    }
}

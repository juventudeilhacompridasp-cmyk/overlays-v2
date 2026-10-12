<?php

namespace Database\Factories;

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    protected $model = Competition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn () => CurrentOrganization::id() ?? Organization::factory()->create()->id,
            'name' => $name = 'Campeonato '.fake()->unique()->city(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'sport' => Sport::Football,
            'description' => null,
        ];
    }
}

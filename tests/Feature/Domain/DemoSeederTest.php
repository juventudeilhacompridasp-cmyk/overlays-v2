<?php

use App\Modules\Competitions\Models\Competition;
use Database\Seeders\DemoCompetitionSeeder;

it('seeds a demo league and prints its standings', function () {
    $this->seed(DemoCompetitionSeeder::class);

    expect(Competition::withoutGlobalScopes()->where('slug', 'campeonato-demo')->count())->toBe(1);

    $this->artisan('competitions:standings')
        ->expectsOutputToContain('Atlético Ilha')
        ->expectsOutputToContain('Suspensos para o próximo jogo')
        ->assertSuccessful();
});

it('does not duplicate the demo competition when seeded twice', function () {
    $this->seed(DemoCompetitionSeeder::class);
    $this->seed(DemoCompetitionSeeder::class);

    expect(Competition::withoutGlobalScopes()->where('slug', 'campeonato-demo')->count())->toBe(1);
});

it('explains how to seed when there is nothing to show', function () {
    $this->artisan('competitions:standings')->assertFailed();
});

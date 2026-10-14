<?php

use App\Models\User;

it('redirects guests to the login page', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with([
    '/painel',
    '/painel/teams',
    '/painel/teams/create',
    '/painel/venues',
    '/painel/competitions',
    '/painel/competitions/create',
]);

it('redirects users with an unverified email', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/painel')
        ->assertRedirect(route('verification.notice'));
});

it('sends the old dashboard route to the panel', function () {
    $this->actingAs(panelUser())
        ->get('/dashboard')
        ->assertRedirect(route('panel.dashboard'));
});

it('shows the panel dashboard with counts', function () {
    $this->actingAs(panelUser())
        ->get('/painel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Panel/Dashboard')
            ->has('counts', fn ($counts) => $counts
                ->where('competitions', 0)->where('teams', 0)->where('people', 0)->where('games_finished', 0))
            ->where('games_by_month.0.value', 0)
            ->where('status_breakdown.scheduled', 0)
            ->has('upcoming_games', 0));
});

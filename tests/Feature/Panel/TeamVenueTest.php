<?php

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Models\Venue;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    // Dados criados pelas fábricas ficam na organização padrão, a mesma que o painel usa nas requisições.
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));

    $this->actingAs(panelUser());
});

it('lists teams with search and entries count', function () {
    Team::factory()->create(['name' => 'Atlético Ilha']);
    Team::factory()->create(['name' => 'Real Cananéia']);

    $this->get('/painel/teams?q=Ilha')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Panel/Teams/Index')
            ->has('teams.data', 1)
            ->where('teams.data.0.name', 'Atlético Ilha')
            ->where('filters.q', 'Ilha'));
});

it('creates, updates and deletes a team', function () {
    $this->post('/painel/teams', ['name' => 'Juventude', 'short_name' => 'JUV', 'city' => 'Registro', 'primary_color' => '#00aa55'])
        ->assertRedirect(route('panel.teams.index'))
        ->assertSessionHas('success');

    $team = Team::query()->where('name', 'Juventude')->firstOrFail();
    expect($team->organization_id)->toBe(CurrentOrganization::id())
        ->and($team->primary_color)->toBe('#00aa55');

    $this->put("/painel/teams/{$team->id}", ['name' => 'Juventude Registro'])
        ->assertRedirect(route('panel.teams.index'));
    expect($team->fresh()->name)->toBe('Juventude Registro');

    $this->delete("/painel/teams/{$team->id}")->assertRedirect(route('panel.teams.index'));
    expect(Team::query()->count())->toBe(0);
});

it('validates team fields and unique names', function () {
    Team::factory()->create(['name' => 'Estrela']);

    $this->post('/painel/teams', ['name' => '', 'primary_color' => 'vermelho'])
        ->assertSessionHasErrors(['name', 'primary_color']);

    $this->post('/painel/teams', ['name' => 'Estrela'])->assertSessionHasErrors('name');

    $other = Team::factory()->create(['name' => 'Outra']);
    $this->put("/painel/teams/{$other->id}", ['name' => 'Outra'])->assertSessionHasNoErrors();
});

it('blocks deleting a team that is entered in a division', function () {
    $entry = Entry::factory()->create();

    $this->delete("/painel/teams/{$entry->team_id}")->assertSessionHas('error');

    expect(Team::query()->whereKey($entry->team_id)->exists())->toBeTrue();
});

it('does not expose teams of another organization', function () {
    $foreign = Team::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    expect(Team::withoutGlobalScopes()->whereKey($foreign->id)->exists())->toBeTrue();

    $this->get("/painel/teams/{$foreign->id}/edit")->assertNotFound();
    $this->delete("/painel/teams/{$foreign->id}")->assertNotFound();
});

it('creates, updates and deletes a venue', function () {
    $this->post('/painel/venues', ['name' => 'Estádio Municipal', 'city' => 'Registro'])
        ->assertRedirect(route('panel.venues.index'));

    $venue = Venue::query()->firstOrFail();

    $this->put("/painel/venues/{$venue->id}", ['name' => 'Estádio Novo', 'address' => 'Rua A, 10'])
        ->assertRedirect(route('panel.venues.index'));
    expect($venue->fresh()->address)->toBe('Rua A, 10');

    $this->post('/painel/venues', ['name' => 'Estádio Novo'])->assertSessionHasErrors('name');

    $this->delete("/painel/venues/{$venue->id}")->assertRedirect(route('panel.venues.index'));
    expect(Venue::query()->count())->toBe(0);
});

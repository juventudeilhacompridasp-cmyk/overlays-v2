<?php

use App\Models\User;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

it('shows a no-access screen to users without a role in the organization', function () {
    $this->actingAs(User::factory()->create())->get('/painel')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Panel/NoAccess'));
});

it('lets every role browse the panel but only the right ones write', function (Role $role, int $teamCreate, int $competitionCreate, int $usersIndex) {
    $this->actingAs(panelUser($role));
    $team = Team::factory()->create();

    $this->get('/painel')->assertOk();
    $this->get('/painel/teams')->assertOk();
    $this->get('/painel/competitions')->assertOk();
    $this->get('/painel/competitions/'.Competition::factory()->create()->id)->assertStatus($competitionCreate === 403 ? 200 : 200);

    $this->get('/painel/teams/create')->assertStatus($teamCreate);
    $this->get("/painel/teams/{$team->id}/edit")->assertStatus($teamCreate);
    $this->get('/painel/competitions/create')->assertStatus($competitionCreate);
    $this->get('/painel/users')->assertStatus($usersIndex);
})->with([
    'administrador' => [Role::Admin, 200, 200, 200],
    'organizador' => [Role::Organizer, 200, 200, 403],
    'operador' => [Role::Operator, 403, 403, 403],
    'consulta' => [Role::Viewer, 403, 403, 403],
]);

it('blocks writes of roles without the permission and sends them back with a message', function (Role $role) {
    $this->actingAs(panelUser($role));

    $this->post('/painel/teams', ['name' => 'Clube Novo'])
        ->assertRedirect()
        ->assertSessionHas('error', 'Você não tem permissão para esta ação.');

    expect(Team::query()->where('name', 'Clube Novo')->exists())->toBeFalse();
})->with([Role::Operator, Role::Viewer]);

it('lets organizers write cadastros and competitions', function () {
    $this->actingAs(panelUser(Role::Organizer));

    $this->post('/painel/teams', ['name' => 'Clube do Organizador'])->assertRedirect(route('panel.teams.index'));
    $this->post('/painel/competitions', ['name' => 'Copa do Organizador'])->assertRedirect();

    expect(Team::query()->where('name', 'Clube do Organizador')->exists())->toBeTrue()
        ->and(Competition::query()->where('name', 'Copa do Organizador')->exists())->toBeTrue();
});

it('lets operators run games but not manage competitions', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    $this->actingAs(panelUser(Role::Operator));

    $this->get("/painel/games/{$game->id}/sheet")->assertOk();
    $this->post("/painel/games/{$game->id}/events", ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1])
        ->assertSessionHas('success');
    $this->post("/painel/games/{$game->id}/periods", ['action' => 'start', 'period' => 1])->assertSessionHas('success');

    // O resultado rápido e o W.O. são do organizador.
    $this->post("/painel/games/{$game->id}/result", ['home_score' => 9, 'away_score' => 0])
        ->assertSessionHas('error', 'Você não tem permissão para esta ação.');
    expect($game->fresh()->home_score)->toBe(1);
});

it('keeps viewers from operating games while they can still read the sheet', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    $this->actingAs(panelUser(Role::Viewer));

    $this->get("/painel/games/{$game->id}/sheet")->assertOk()->assertInertia(fn ($page) => $page->where('auth.role', 'viewer')->where('auth.can.operate-games', false));
    $this->post("/painel/games/{$game->id}/events", ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1])
        ->assertSessionHas('error', 'Você não tem permissão para esta ação.');

    expect($game->fresh()->home_score)->toBe(0);
});

it('shows the forbidden page instead of a bare 403 when opening a restricted screen', function () {
    $this->actingAs(panelUser(Role::Operator))->get('/painel/teams/create')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Errors/Forbidden'));
});

it('shares the role and the abilities with the front end', function (Role $role, array $can) {
    $this->actingAs(panelUser($role))->get('/painel')->assertInertia(fn ($page) => $page
        ->where('auth.role', $role->value)
        ->where('auth.role_label', $role->label())
        ->where('auth.can.manage-registry', $can[0])
        ->where('auth.can.manage-competitions', $can[1])
        ->where('auth.can.operate-games', $can[2])
        ->where('auth.can.manage-users', $can[3]));
})->with([
    [Role::Admin, [true, true, true, true]],
    [Role::Organizer, [true, true, true, false]],
    [Role::Operator, [false, false, true, false]],
    [Role::Viewer, [false, false, false, false]],
]);

it('resolves gates from the role in the current organization only', function () {
    $user = panelUser(Role::Admin);

    expect(Gate::forUser($user)->allows('manage-users'))->toBeTrue();

    // Em outra organização o mesmo usuário não tem papel nenhum.
    $user->forgetRole();
    CurrentOrganization::set(Organization::factory()->create(['slug' => 'outra']));

    expect(Gate::forUser($user)->allows('manage-users'))->toBeFalse()->and($user->role())->toBeNull();
});

it('does not let a member of another organization in', function () {
    $user = panelUser(Role::Admin);
    Membership::query()->where('user_id', $user->id)->update(['organization_id' => Organization::factory()->create(['slug' => 'outra'])->id]);

    $this->actingAs($user)->get('/painel')->assertForbidden()->assertInertia(fn ($page) => $page->component('Panel/NoAccess'));
});

it('keeps unknown routes and records of other organizations at 404 before any permission check', function () {
    $game = Game::factory()->create();
    CurrentOrganization::set(Organization::factory()->create(['slug' => 'outra']));
    config(['platform.default_organization_slug' => 'outra']);

    $this->actingAs(panelUser(Role::Admin))->get("/painel/games/{$game->id}/sheet")->assertNotFound();
});

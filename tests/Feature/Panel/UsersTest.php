<?php

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

it('lists members with their roles and what each role can do', function () {
    $admin = panelUser(Role::Admin);
    panelUser(Role::Viewer);

    $this->actingAs($admin)->get('/painel/users')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Panel/Users/Index')
        ->has('members', 2)
        ->has('roles', 4)
        ->where('members', fn ($members) => collect($members)->contains(fn ($member) => $member['is_me'] === true && $member['role'] === 'admin')));
});

it('creates a verified user with the chosen role', function () {
    $this->actingAs(panelUser(Role::Admin));

    $this->post('/painel/users', ['name' => 'Maria Mesária', 'email' => 'maria@exemplo.com', 'password' => 'senha-segura-1', 'role' => 'operator'])
        ->assertSessionHas('success');

    $user = User::query()->where('email', 'maria@exemplo.com')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('senha-segura-1', $user->password))->toBeTrue()
        ->and($user->role())->toBe(Role::Operator);

    // E já consegue entrar no painel.
    auth()->logout();
    $this->post('/login', ['email' => 'maria@exemplo.com', 'password' => 'senha-segura-1']);
    $this->get('/painel')->assertOk();
});

it('validates the new user', function () {
    $this->actingAs(panelUser(Role::Admin));
    $existing = User::factory()->create();

    $this->post('/painel/users', ['name' => '', 'email' => 'invalido', 'password' => '123', 'role' => 'dono'])
        ->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    $this->post('/painel/users', ['name' => 'Repetido', 'email' => $existing->email, 'password' => 'senha-segura-1', 'role' => 'viewer'])
        ->assertSessionHasErrors(['email' => 'O valor informado em e-mail já está em uso.']);
});

it('changes a role and the change takes effect on the next request', function () {
    $admin = panelUser(Role::Admin);
    $target = panelUser(Role::Viewer);
    $membership = Membership::query()->where('user_id', $target->id)->firstOrFail();

    $this->actingAs($admin)->patch("/painel/users/{$membership->id}", ['role' => 'organizer'])->assertSessionHas('success', 'Papel atualizado.');

    expect(Membership::query()->find($membership->id)->role)->toBe(Role::Organizer);

    $this->post('/painel/users', [])->assertSessionHasErrors();
    $this->actingAs(User::query()->find($target->id))->post('/painel/teams', ['name' => 'Agora Pode'])->assertRedirect(route('panel.teams.index'));
});

it('never leaves the organization without an administrator', function () {
    $admin = panelUser(Role::Admin);
    $membership = Membership::query()->where('user_id', $admin->id)->firstOrFail();

    $this->actingAs($admin)->patch("/painel/users/{$membership->id}", ['role' => 'viewer'])
        ->assertSessionHas('error', 'A organização precisa ter pelo menos um administrador.');
    $this->delete("/painel/users/{$membership->id}")
        ->assertSessionHas('error', 'A organização precisa ter pelo menos um administrador.');

    expect(Membership::query()->find($membership->id)->role)->toBe(Role::Admin);

    // Com um segundo administrador já pode sair.
    panelUser(Role::Admin);
    $this->delete("/painel/users/{$membership->id}")->assertSessionHas('success');
    expect(Membership::query()->find($membership->id))->toBeNull();
});

it('removes access without deleting the account', function () {
    $admin = panelUser(Role::Admin);
    $target = panelUser(Role::Organizer);
    $membership = Membership::query()->where('user_id', $target->id)->firstOrFail();

    $this->actingAs($admin)->delete("/painel/users/{$membership->id}")->assertSessionHas('success');

    expect(User::query()->find($target->id))->not->toBeNull();

    $this->actingAs(User::query()->find($target->id))->get('/painel')->assertForbidden();
});

it('does not let non-admins touch users, not even to read the list', function (Role $role) {
    $target = Membership::query()->where('user_id', panelUser(Role::Admin)->id)->firstOrFail();
    $this->actingAs(panelUser($role));

    $this->get('/painel/users')->assertForbidden();
    $this->post('/painel/users', ['name' => 'X', 'email' => 'x@x.com', 'password' => 'senha-segura-1', 'role' => 'admin'])->assertSessionHas('error');
    $this->patch("/painel/users/{$target->id}", ['role' => 'viewer'])->assertSessionHas('error');
    $this->delete("/painel/users/{$target->id}")->assertSessionHas('error');

    expect(User::query()->where('email', 'x@x.com')->exists())->toBeFalse()->and($target->fresh()->role)->toBe(Role::Admin);
})->with([Role::Organizer, Role::Operator, Role::Viewer]);

it('only manages members of the current organization', function () {
    $admin = panelUser(Role::Admin);
    $other = Organization::factory()->create(['slug' => 'outra']);
    $foreign = Membership::query()->create(['organization_id' => $other->id, 'user_id' => User::factory()->create()->id, 'role' => Role::Admin]);

    $this->actingAs($admin)->delete("/painel/users/{$foreign->id}")->assertNotFound();
    $this->patch("/painel/users/{$foreign->id}", ['role' => 'viewer'])->assertNotFound();
});

it('closes public registration once the installation has users and opens only for the very first one', function () {
    $this->get('/register')->assertOk();

    $this->post('/register', ['name' => 'Primeiro', 'email' => 'primeiro@exemplo.com', 'password' => 'senha-segura-1', 'password_confirmation' => 'senha-segura-1'])->assertRedirect();
    $first = User::query()->where('email', 'primeiro@exemplo.com')->firstOrFail();
    expect($first->role())->toBe(Role::Admin);

    $this->post('/logout');
    $this->get('/register')->assertForbidden();
    $this->post('/register', ['name' => 'Intruso', 'email' => 'intruso@exemplo.com', 'password' => 'senha-segura-1', 'password_confirmation' => 'senha-segura-1'])
        ->assertSessionHas('error', 'O cadastro está fechado. Peça a um administrador para criar seu acesso.');

    expect(User::query()->where('email', 'intruso@exemplo.com')->exists())->toBeFalse();
});

it('can reopen registration, but new accounts start without access', function () {
    panelUser(Role::Admin);
    config(['platform.open_registration' => true]);

    $this->post('/register', ['name' => 'Curioso', 'email' => 'curioso@exemplo.com', 'password' => 'senha-segura-1', 'password_confirmation' => 'senha-segura-1'])->assertRedirect();

    $user = User::query()->where('email', 'curioso@exemplo.com')->firstOrFail();
    expect($user->role())->toBeNull();

    // Depois de verificar o e-mail, a conta nova cai na tela "sem acesso".
    $user->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($user)->get('/painel')->assertForbidden()->assertInertia(fn ($page) => $page->component('Panel/NoAccess'));
});

it('gives the seeded demo admin the administrator role', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@plataforma.test')->firstOrFail();
    CurrentOrganization::set(Organization::query()->where('slug', config('platform.default_organization_slug'))->firstOrFail());

    expect($admin->role())->toBe(Role::Admin);
});

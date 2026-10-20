<?php

use App\Models\User;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;

it('lets the very first visitor register by default (development) and makes him administrator', function () {
    $this->get('/register')->assertOk();
});

it('closes the first-user door on a public installation, so nobody takes it by arriving first', function () {
    config(['platform.first_user_setup' => false, 'platform.open_registration' => false]);

    expect(User::query()->count())->toBe(0);

    $this->get('/register')->assertForbidden();
    // Ação (não-GET) sem permissão volta com aviso em vez de uma 403 crua (bootstrap/app.php); o que importa é nada ser criado.
    $this->post('/register', ['name' => 'Intruso', 'email' => 'intruso@example.com', 'password' => 'SenhaForte12345', 'password_confirmation' => 'SenhaForte12345'])
        ->assertRedirect()
        ->assertSessionHas('error');
    $this->assertGuest();

    expect(User::query()->count())->toBe(0)->and(Membership::query()->count())->toBe(0);
});

it('still honors an explicit OPEN_REGISTRATION', function () {
    config(['platform.first_user_setup' => false, 'platform.open_registration' => true]);

    $this->get('/register')->assertOk();
});

it('creates the first administrator by command, verified and with the administrator role', function () {
    $this->artisan('platform:create-admin', ['email' => 'Dono@Example.com', '--name' => 'Dono', '--password' => 'SenhaForte12345', '--platform' => true])
        ->expectsOutputToContain('dono@example.com')
        ->assertSuccessful();

    $user = User::query()->where('email', 'dono@example.com')->firstOrFail();
    $organization = Organization::query()->where('slug', config('platform.default_organization_slug'))->firstOrFail();
    $membership = Membership::query()->withoutGlobalScopes()->where('user_id', $user->getKey())->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->is_platform_admin)->toBeTrue()
        ->and($membership->role)->toBe(Role::Admin)
        ->and($membership->organization_id)->toBe($organization->getKey());
});

it('refuses a weak password and creates nothing', function () {
    $this->artisan('platform:create-admin', ['email' => 'fraco@example.com', '--password' => 'curta'])->assertFailed();

    expect(User::query()->where('email', 'fraco@example.com')->exists())->toBeFalse();
});

it('promotes an existing user without touching the password', function () {
    $user = User::factory()->create(['email' => 'ja@example.com']);
    $hash = $user->password;

    $this->artisan('platform:create-admin', ['email' => 'ja@example.com'])->assertSuccessful();

    expect($user->fresh()->password)->toBe($hash)
        ->and(Membership::query()->withoutGlobalScopes()->where('user_id', $user->getKey())->value('role'))->toBe(Role::Admin);
});

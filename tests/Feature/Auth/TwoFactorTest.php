<?php

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use App\Support\Auth\Totp;
use App\Support\Auth\TwoFactor;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** Usuário com dois fatores já confirmados; devolve também o segredo para gerar códigos. */
function twoFactorUser(?User $user = null): array
{
    $user ??= User::factory()->create();
    $secret = TwoFactor::begin($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => []])->save();

    return [$user->fresh(), $secret];
}

/** Código válido num passo à frente do atual (o passo atual pode ter sido usado), aceito pela janela de ±1. */
function nextCode(string $secret): string
{
    return Totp::code($secret, step: intdiv(time(), Totp::STEP) + 1);
}

describe('activation in the profile', function () {
    it('asks for the account password, then shows the pending secret and QR address', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.two-factor.begin'), ['password' => 'errada'])->assertSessionHasErrors('password');
        expect($user->fresh()->two_factor_secret)->toBeNull();

        $this->actingAs($user)->post(route('profile.two-factor.begin'), ['password' => 'password'])->assertRedirect(route('profile.edit'));

        $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->where('twoFactor.pending', true)
            ->where('twoFactor.enabled', false)
            ->where('twoFactor.secret', $user->fresh()->two_factor_secret)
            ->where('twoFactor.otpauth', fn ($uri) => str_starts_with($uri, 'otpauth://totp/')));
    });

    it('only turns on after a correct code, and shows the recovery codes once', function () {
        $user = User::factory()->create();
        $secret = TwoFactor::begin($user);

        $this->actingAs($user)->post(route('profile.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();

        $this->actingAs($user)->post(route('profile.two-factor.confirm'), ['code' => Totp::code($secret)])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('recovery_codes', fn (array $codes) => count($codes) === 8);

        $fresh = $user->fresh();
        expect($fresh->hasTwoFactorEnabled())->toBeTrue()
            ->and(TwoFactor::recoveryCodesLeft($fresh))->toBe(8);

        // A primeira abertura do perfil depois de ativar mostra os códigos (flash); a seguinte já não mostra.
        $this->actingAs($fresh)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
            ->where('twoFactor.enabled', true)
            ->where('twoFactor.secret', null)
            ->where('twoFactor.otpauth', null)
            ->has('twoFactor.recovery_codes', 8)
            ->where('twoFactor.recovery_codes_left', 8));

        $this->actingAs($fresh)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
            ->where('twoFactor.recovery_codes', null)
            ->missing('auth.user.two_factor_secret')
            ->missing('auth.user.two_factor_recovery_codes'));
    });

    it('stores the secret and the recovery codes encrypted and never as plain text', function () {
        [$user, $secret] = twoFactorUser();
        TwoFactor::regenerateRecoveryCodes($user);

        $raw = (array) DB::table('users')->where('id', $user->id)->first();

        expect($raw['two_factor_secret'])->not->toContain($secret)
            ->and($raw['two_factor_recovery_codes'])->not->toContain('-');
    });

    it('renews the recovery codes and disables with the password', function () {
        [$user] = twoFactorUser();

        $this->actingAs($user)->post(route('profile.two-factor.recovery'), ['password' => 'errada'])->assertSessionHasErrors('password');
        $this->actingAs($user)->post(route('profile.two-factor.recovery'), ['password' => 'password'])->assertSessionHas('recovery_codes');
        expect(TwoFactor::recoveryCodesLeft($user->fresh()))->toBe(8);

        $this->actingAs($user)->delete(route('profile.two-factor.disable'), ['password' => 'errada'])->assertSessionHasErrors('password');
        expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

        $this->actingAs($user)->delete(route('profile.two-factor.disable'), ['password' => 'password'])->assertRedirect(route('profile.edit'));
        $fresh = $user->fresh();
        expect($fresh->hasTwoFactorEnabled())->toBeFalse()
            ->and($fresh->two_factor_secret)->toBeNull();
    });

    it('is closed to guests', function () {
        $this->post(route('profile.two-factor.begin'), ['password' => 'password'])->assertRedirect('/login');
        $this->post(route('profile.two-factor.confirm'), ['code' => '123456'])->assertRedirect('/login');
    });
});

describe('login with two factors', function () {
    it('does not open the session after the password: asks for the code first', function () {
        [$user] = twoFactorUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertOk()->assertInertia(fn ($page) => $page->component('Auth/TwoFactorChallenge'));
    });

    it('still rejects a wrong password before any second factor', function () {
        [$user] = twoFactorUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    });

    it('logs in with a correct app code and refuses the same code twice', function () {
        [$user, $secret] = twoFactorUser();
        $code = nextCode($secret);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    });

    it('refuses a wrong code and blocks after five attempts', function () {
        [$user, $secret] = twoFactorUser();
        RateLimiter::clear('two-factor|'.$user->id.'|127.0.0.1');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        foreach (range(1, 5) as $ignored) {
            $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->post(route('two-factor.verify'), ['code' => nextCode($secret)])->assertSessionHasErrors('code');
        $this->assertGuest();
    });

    it('accepts a recovery code once', function () {
        [$user] = twoFactorUser();
        $codes = TwoFactor::regenerateRecoveryCodes($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['recovery_code' => strtoupper($codes[0])])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        expect(TwoFactor::recoveryCodesLeft($user->fresh()))->toBe(7);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    });

    it('expires the pending verification after ten minutes and needs the session first', function () {
        [$user, $secret] = twoFactorUser();

        $this->post(route('two-factor.verify'), ['code' => nextCode($secret)])->assertRedirect(route('login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->travel(11)->minutes();
        $this->post(route('two-factor.verify'), ['code' => nextCode($secret)])->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('needs the code or the recovery code in the request', function () {
        [$user] = twoFactorUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post(route('two-factor.verify'), [])->assertSessionHasErrors(['code', 'recovery_code']);
    });
});

describe('required for administrators', function () {
    it('sends an administrator without two factors to the profile when the installation requires it', function () {
        config(['platform.two_factor.required_for_admins' => true]);
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->get('/painel')->assertRedirect(route('profile.edit'));
        $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
    });

    it('lets the administrator in once two factors are on, and never blocks who does not administer users', function () {
        config(['platform.two_factor.required_for_admins' => true]);
        $admin = panelUser(Role::Admin);
        twoFactorUser($admin);

        $this->actingAs($admin->fresh())->get('/painel')->assertOk();
        $this->actingAs(panelUser(Role::Operator))->get('/painel')->assertOk();
        $this->actingAs(panelUser(Role::Organizer))->get('/painel')->assertOk();
    });

    it('also covers the platform area, and nothing is required by default', function () {
        $super = User::factory()->create();
        $super->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($super)->get('/plataforma')->assertOk();

        config(['platform.two_factor.required_for_admins' => true]);

        $this->actingAs($super)->get('/plataforma')->assertRedirect(route('profile.edit'));
    });
});

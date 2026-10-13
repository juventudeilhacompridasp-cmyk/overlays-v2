<?php

use App\Models\User;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function platformAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

function withPlan(Plan $plan, ?array $features = null, ?array $limits = null): Organization
{
    $organization = CurrentOrganization::get();
    $organization->update(['plan' => $plan->value, 'features' => $features, 'limits' => $limits]);

    return $organization->fresh();
}

describe('packages and features', function () {
    it('derives the features from the package unless they were released by hand', function () {
        $organization = Organization::factory()->create(['plan' => 'basic']);
        expect($organization->featureKeys())->toBe([])
            ->and($organization->hasFeature(Feature::Reports))->toBeFalse();

        $organization->update(['plan' => 'standard']);
        expect($organization->hasFeature(Feature::OnlineRegistration))->toBeTrue()
            ->and($organization->hasFeature(Feature::Overlays))->toBeFalse();

        $organization->update(['plan' => 'pro']);
        expect($organization->featureKeys())->toHaveCount(count(Feature::cases()));

        $organization->update(['features' => ['overlays', 'invalido']]);
        expect($organization->fresh()->featureKeys())->toBe(['overlays']);
    });

    it('reads limits and treats empty as unlimited', function () {
        $organization = withPlan(Plan::Pro, null, ['max_users' => 3]);

        expect($organization->limit('max_users'))->toBe(3)->and($organization->limit('max_competitions'))->toBeNull();
    });

    it('closes the public registration (404) and the panel areas (403) when the feature is off', function () {
        $division = Division::factory()->create();
        $season = Season::query()->findOrFail($division->season_id);
        $season->update(['status' => 'registration']);
        $url = "/inscricoes/{$season->competition->slug}/{$season->id}";

        $this->get($url)->assertOk();

        withPlan(Plan::Basic);
        $this->get($url)->assertNotFound();
        $this->get('/minha-inscricao/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/carteirinha/ABCDEFGHJK')->assertNotFound();

        $this->actingAs(panelUser(Role::Admin));
        foreach (['/painel/registrations', '/painel/reports', '/painel/broadcast'] as $panelUrl) {
            $this->get($panelUrl)->assertForbidden();
        }
    });

    it('hides the registration card, the portal call-to-action and the menu items without the feature', function () {
        $division = Division::factory()->create();
        $season = Season::query()->findOrFail($division->season_id);
        $season->update(['status' => 'registration']);
        $this->actingAs(panelUser(Role::Admin));

        $this->get("/painel/seasons/{$season->id}")->assertInertia(fn ($page) => $page->where('registration.open', true)->has('features', 6));

        withPlan(Plan::Basic);
        $this->get("/painel/seasons/{$season->id}")->assertInertia(fn ($page) => $page->where('registration', null)->where('features', []));
    });

    it('keeps the default organization fully enabled', function () {
        expect(CurrentOrganization::has(Feature::Overlays))->toBeTrue();
        $this->get('/')->assertOk();
    });
});

describe('limits', function () {
    it('refuses new users beyond the package limit', function () {
        $admin = panelUser(Role::Admin);
        withPlan(Plan::Pro, null, ['max_users' => 1]);
        $this->actingAs($admin);

        $this->post('/painel/users', ['name' => 'Novo', 'email' => 'novo@exemplo.com', 'password' => 'senha-forte-123', 'password_confirmation' => 'senha-forte-123', 'role' => 'viewer'])
            ->assertSessionHas('error', 'O pacote da organização permite no máximo 1 usuários.');

        expect(Membership::query()->count())->toBe(1);
    });

    it('refuses new competitions beyond the package limit', function () {
        $this->actingAs(panelUser(Role::Organizer));
        withPlan(Plan::Pro, null, ['max_competitions' => 1]);
        Competition::factory()->create();

        $this->post('/painel/competitions', ['name' => 'Copa Nova'])
            ->assertSessionHas('error', 'O pacote da organização permite no máximo 1 competições.');

        expect(Competition::query()->count())->toBe(1);
    });
});

describe('platform area', function () {
    it('hides the area from everyone but platform admins', function () {
        $this->get('/plataforma')->assertRedirect('/login');

        $this->actingAs(panelUser(Role::Admin));
        $this->get('/plataforma')->assertNotFound();
        $this->patch('/plataforma/organizations/'.CurrentOrganization::id(), ['plan' => 'basic', 'custom_features' => false])->assertNotFound();
    });

    it('lists the organizations with plan and usage metrics', function () {
        $other = Organization::factory()->create(['name' => 'Liga Vizinha', 'plan' => 'basic']);
        CurrentOrganization::using($other, fn () => Competition::factory()->create());
        panelUser(Role::Admin);
        $this->actingAs(platformAdmin());

        $this->get('/plataforma')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/Index')
            ->has('organizations', 2)
            ->where('organizations', fn ($rows) => collect($rows)->firstWhere('name', 'Liga Vizinha')['usage']['competitions'] === 1
                && collect($rows)->firstWhere('name', 'Liga Vizinha')['plan_label'] === 'Básico'
                && collect($rows)->firstWhere('slug', config('platform.default_organization_slug'))['usage']['users'] === 1));
    });

    it('changes package, hand-picked features and limits', function () {
        $organization = Organization::factory()->create(['plan' => 'basic']);
        $this->actingAs(platformAdmin());

        $this->get("/plataforma/organizations/{$organization->id}")->assertOk()->assertInertia(fn ($page) => $page->where('organization.plan', 'basic')->where('organization.custom_features', false));

        $this->patch("/plataforma/organizations/{$organization->id}", ['plan' => 'standard', 'custom_features' => false, 'max_users' => '5', 'max_competitions' => ''])->assertSessionHas('success');
        $organization->refresh();
        expect($organization->plan())->toBe(Plan::Standard)->and($organization->features)->toBeNull()->and($organization->limits)->toBe(['max_users' => 5]);

        $this->patch("/plataforma/organizations/{$organization->id}", ['plan' => 'basic', 'custom_features' => true, 'features' => ['overlays', 'overlays', 'reports']])->assertSessionHas('success');
        $organization->refresh();
        expect($organization->features)->toBe(['overlays', 'reports'])->and($organization->limits)->toBeNull();
    });

    it('validates the package, features and limits', function () {
        $organization = Organization::factory()->create();
        $this->actingAs(platformAdmin());

        $this->patch("/plataforma/organizations/{$organization->id}", ['plan' => 'ouro', 'custom_features' => true, 'features' => ['voar'], 'max_users' => '0'])
            ->assertSessionHasErrors(['plan', 'features.0', 'max_users']);
    });

    it('grants and revokes platform access by command', function () {
        $user = User::factory()->create(['email' => 'dono@exemplo.com']);

        Artisan::call('platform:make-admin', ['email' => 'dono@exemplo.com']);
        expect($user->fresh()->is_platform_admin)->toBeTrue();

        Artisan::call('platform:make-admin', ['email' => 'dono@exemplo.com', '--revoke' => true]);
        expect($user->fresh()->is_platform_admin)->toBeFalse()
            ->and(Artisan::call('platform:make-admin', ['email' => 'ninguem@exemplo.com']))->toBe(1);
    });
});

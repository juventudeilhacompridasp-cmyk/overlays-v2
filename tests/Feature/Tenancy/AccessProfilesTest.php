<?php

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateOrganization;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Ability;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\AccessProfile;
use App\Modules\Tenancy\Models\AccessProfilePermission;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\SystemProfiles;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

it('maps every ability to exactly one permission', function () {
    foreach (Ability::cases() as $ability) {
        expect(Permission::forAbility($ability)->ability())->toBe($ability);
    }

    expect(Permission::cases())->toHaveCount(count(Ability::cases()));
});

it('keeps the role matrix when the member has no profile', function () {
    foreach ([Role::Admin, Role::Organizer, Role::Operator, Role::Viewer, Role::TeamManager] as $role) {
        $user = panelUser($role);

        foreach (Ability::cases() as $ability) {
            expect(Gate::forUser($user)->allows($ability->value))->toBe($role->can($ability), "{$role->value} {$ability->value}");
        }
    }
});

it('uses the profile permissions when the member has a profile', function () {
    SystemProfiles::ensure(CurrentOrganization::id());
    $profile = AccessProfile::query()->create(['name' => 'Mesário que aprova', 'slug' => 'mesario-aprova', 'is_system' => false]);
    AccessProfilePermission::query()->insert([
        ['profile_id' => $profile->getKey(), 'permission' => Permission::GamesOperate->value],
        ['profile_id' => $profile->getKey(), 'permission' => Permission::RegistryManage->value],
        ['profile_id' => $profile->getKey(), 'permission' => 'modulo.que-nao-existe'],
    ]);

    $user = panelUser(Role::Viewer);
    Membership::query()->where('user_id', $user->getKey())->update(['access_profile_id' => $profile->getKey()]);

    expect(Gate::forUser($user)->allows('operate-games'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manage-registry'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manage-competitions'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('manage-users'))->toBeFalse()
        ->and($user->permissions())->toHaveCount(2);
});

it('never gives panel permissions to a team manager, not even through a profile', function () {
    SystemProfiles::ensure(CurrentOrganization::id());
    $admin = AccessProfile::query()->where('slug', 'admin')->firstOrFail();

    $user = panelUser(Role::TeamManager);
    Membership::query()->where('user_id', $user->getKey())->update(['access_profile_id' => $admin->getKey()]);

    expect($user->permissions())->toBe([])
        ->and(Gate::forUser($user)->allows('manage-users'))->toBeFalse();
});

it('creates system profiles mirroring the roles, once', function () {
    $organizationId = CurrentOrganization::id();

    SystemProfiles::ensure($organizationId);
    $permissions = AccessProfile::query()->where('slug', 'organizer')->firstOrFail()->permissions();
    SystemProfiles::ensure($organizationId);

    expect(AccessProfile::query()->count())->toBe(4)
        ->and(AccessProfile::query()->where('slug', 'manager')->exists())->toBeFalse()
        ->and(AccessProfile::query()->where('is_system', false)->exists())->toBeFalse()
        ->and($permissions)->toEqualCanonicalizing(Role::Organizer->permissions());
});

it('seeds the system profiles when the platform creates an organization', function () {
    $result = app(CreateOrganization::class)->handle('Liga Nova', 'liga-nova', Plan::Pro, 'Ana', 'ana@liga.test');

    CurrentOrganization::using($result['organization'], function () {
        expect(AccessProfile::query()->pluck('slug')->sort()->values()->all())->toBe(['admin', 'operator', 'organizer', 'viewer']);
    });

    expect(User::query()->where('email', 'ana@liga.test')->exists())->toBeTrue();
});

<?php

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\AccessProfile;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\SystemProfiles;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function makeProfile(string $name, array $permissions): AccessProfile
{
    $profile = AccessProfile::query()->create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'is_system' => false]);
    $profile->grants()->createMany(array_map(fn ($permission) => ['permission' => $permission->value], $permissions));

    return $profile;
}

function memberWithProfile(AccessProfile $profile): User
{
    $user = panelUser(Role::Viewer);
    Membership::query()->where('user_id', $user->getKey())->update(['access_profile_id' => $profile->getKey()]);

    return $user->fresh();
}

describe('the profiles screen', function () {
    it('is for those who manage users only, and lists the system profiles', function () {
        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.profiles.index'))->assertForbidden();

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.profiles.index'))->assertInertia(fn ($page) => $page
            ->component('Panel/Profiles/Index')
            ->has('profiles', 4)
            ->where('profiles.0.is_system', true));
    });

    it('creates a profile with the chosen permissions and refuses unknown ones and repeated names', function () {
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->post(route('panel.profiles.store'), ['name' => 'Mesário que cadastra', 'description' => 'Mesa + cadastros', 'permissions' => ['games.operate', 'registry.manage']])
            ->assertRedirect(route('panel.profiles.index'));

        $profile = AccessProfile::query()->where('slug', 'mesario-que-cadastra')->firstOrFail();
        expect($profile->is_system)->toBeFalse()
            ->and($profile->permissions())->toEqualCanonicalizing([Permission::GamesOperate, Permission::RegistryManage]);

        $this->actingAs($admin)->post(route('panel.profiles.store'), ['name' => 'X', 'permissions' => ['inventada.tudo']])->assertSessionHasErrors('permissions.0');
        $this->actingAs($admin)->post(route('panel.profiles.store'), ['name' => 'MESÁRIO QUE CADASTRA'])->assertSessionHas('error');

        expect(AccessProfile::query()->where('is_system', false)->count())->toBe(1);
    });

    it('edits a custom profile but never a system one', function () {
        $admin = panelUser(Role::Admin);
        $profile = makeProfile('Meu perfil', [Permission::GamesOperate]);

        $this->actingAs($admin)->patch(route('panel.profiles.update', $profile), ['name' => 'Meu perfil 2', 'permissions' => ['registry.manage']])->assertSessionHas('success');
        expect($profile->fresh()->name)->toBe('Meu perfil 2')
            ->and($profile->fresh(['grants'])->permissions())->toBe([Permission::RegistryManage]);

        SystemProfiles::ensure(CurrentOrganization::id());
        $system = AccessProfile::query()->where('slug', 'operator')->firstOrFail();

        $this->actingAs($admin)->get(route('panel.profiles.edit', $system))->assertRedirect(route('panel.profiles.index'));
        $this->actingAs($admin)->patch(route('panel.profiles.update', $system), ['name' => 'Hack', 'permissions' => ['users.manage']])->assertSessionHas('error');
        expect($system->fresh()->name)->not->toBe('Hack');
    });

    it('duplicates a profile, including a system one, so it can be customized', function () {
        $admin = panelUser(Role::Admin);
        SystemProfiles::ensure(CurrentOrganization::id());
        $system = AccessProfile::query()->where('slug', 'organizer')->firstOrFail();

        $this->actingAs($admin)->post(route('panel.profiles.duplicate', $system));
        $this->actingAs($admin)->post(route('panel.profiles.duplicate', $system));

        $copies = AccessProfile::query()->where('is_system', false)->orderBy('name')->get();
        expect($copies->pluck('name')->all())->toBe(['Organizador (cópia)', 'Organizador (cópia) 2'])
            ->and($copies[0]->permissions())->toEqualCanonicalizing(Role::Organizer->permissions());
    });

    it('deletes an unused custom profile and refuses one in use or a system one', function () {
        $admin = panelUser(Role::Admin);
        $free = makeProfile('Livre', []);
        $used = makeProfile('Em uso', [Permission::GamesOperate]);
        memberWithProfile($used);
        SystemProfiles::ensure(CurrentOrganization::id());
        $system = AccessProfile::query()->where('slug', 'viewer')->firstOrFail();

        $this->actingAs($admin)->delete(route('panel.profiles.destroy', $free))->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('panel.profiles.destroy', $used))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('panel.profiles.destroy', $system))->assertSessionHas('error');

        expect(AccessProfile::query()->whereKey($free->getKey())->exists())->toBeFalse()
            ->and(AccessProfile::query()->whereKey($used->getKey())->exists())->toBeTrue()
            ->and(AccessProfile::query()->whereKey($system->getKey())->exists())->toBeTrue();
    });
});

describe('assigning a profile to a user', function () {
    it('changes what the user can do and what the screens show', function () {
        $admin = panelUser(Role::Admin);
        $profile = makeProfile('Meu perfil', [Permission::GamesOperate, Permission::RegistryManage]);
        $user = panelUser(Role::Viewer);
        $membership = Membership::query()->where('user_id', $user->getKey())->firstOrFail();

        $this->actingAs($admin)->patch(route('panel.users.update', $membership), ['profile_id' => $profile->getKey()])->assertSessionHas('success');

        $membership->refresh();
        expect($membership->access_profile_id)->toBe($profile->getKey())
            ->and($membership->role)->toBe(Role::Viewer);

        $fresh = User::query()->findOrFail($user->getKey());
        expect(Gate::forUser($fresh)->allows('operate-games'))->toBeTrue()
            ->and(Gate::forUser($fresh)->allows('manage-registry'))->toBeTrue()
            ->and(Gate::forUser($fresh)->allows('manage-competitions'))->toBeFalse();

        $this->actingAs($fresh)->get(route('panel.dashboard'))->assertInertia(fn ($page) => $page
            ->where('auth.can.operate-games', true)
            ->where('auth.can.manage-users', false)
            ->where('auth.role_label', 'Meu perfil'));
    });

    it('goes back to a standard role, which clears the profile', function () {
        $admin = panelUser(Role::Admin);
        $user = memberWithProfile(makeProfile('Meu perfil', [Permission::GamesOperate]));
        $membership = Membership::query()->where('user_id', $user->getKey())->firstOrFail();

        $this->actingAs($admin)->patch(route('panel.users.update', $membership), ['role' => 'organizer'])->assertSessionHas('success');

        expect($membership->fresh()->access_profile_id)->toBeNull()
            ->and($membership->fresh()->role)->toBe(Role::Organizer);
    });

    it('creates a user already with a profile, and refuses a profile of another organization', function () {
        $admin = panelUser(Role::Admin);
        $profile = makeProfile('Meu perfil', [Permission::GamesOperate]);

        $this->actingAs($admin)->post(route('panel.users.store'), ['name' => 'Ana', 'email' => 'ana@ex.com', 'password' => 'senha-forte-123', 'profile_id' => $profile->getKey()])->assertSessionHas('success');

        $created = Membership::query()->whereHas('user', fn ($query) => $query->where('email', 'ana@ex.com'))->firstOrFail();
        expect($created->access_profile_id)->toBe($profile->getKey());

        $other = Organization::factory()->create(['slug' => 'outra']);
        $foreign = AccessProfile::query()->create(['organization_id' => $other->getKey(), 'name' => 'De fora', 'slug' => 'de-fora', 'is_system' => false]);

        $this->actingAs($admin)->post(route('panel.users.store'), ['name' => 'Bia', 'email' => 'bia@ex.com', 'password' => 'senha-forte-123', 'profile_id' => $foreign->getKey()])->assertSessionHasErrors('profile_id');
    });
});

describe('the user management guard', function () {
    it('keeps someone who manages users, whether by role or by profile', function () {
        $adminProfile = makeProfile('Gestor total', [Permission::UsersManage, Permission::GamesOperate]);
        $holder = memberWithProfile($adminProfile);
        $membership = Membership::query()->where('user_id', $holder->getKey())->firstOrFail();

        // O único que gerencia usuários é este perfil: não pode perder a permissão, trocar de acesso nem sair.
        $this->actingAs($holder)->patch(route('panel.profiles.update', $adminProfile), ['name' => 'Gestor total', 'permissions' => ['games.operate']])->assertSessionHas('error');
        $this->actingAs($holder)->patch(route('panel.users.update', $membership), ['role' => 'operator'])->assertSessionHas('error');
        $this->actingAs($holder)->delete(route('panel.users.destroy', $membership))->assertSessionHas('error');

        expect($adminProfile->fresh(['grants'])->permissions())->toContain(Permission::UsersManage)
            ->and($membership->fresh()->access_profile_id)->toBe($adminProfile->getKey());

        // Com um administrador de papel, o perfil pode mudar.
        panelUser(Role::Admin);
        $this->actingAs($holder->fresh())->patch(route('panel.profiles.update', $adminProfile), ['name' => 'Gestor total', 'permissions' => ['games.operate']])->assertSessionHas('success');
    });

    it('still protects the last role-based administrator', function () {
        $admin = panelUser(Role::Admin);
        $membership = Membership::query()->where('user_id', $admin->getKey())->firstOrFail();
        $profile = makeProfile('Só jogos', [Permission::GamesOperate]);

        $this->actingAs($admin)->patch(route('panel.users.update', $membership), ['profile_id' => $profile->getKey()])->assertSessionHas('error');
        expect($membership->fresh()->role)->toBe(Role::Admin);
    });
});

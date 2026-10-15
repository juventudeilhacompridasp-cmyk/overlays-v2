<?php

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\AccessAudit;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function overridesMembershipOf($user): Membership
{
    return Membership::query()->where('user_id', $user->getKey())->firstOrFail();
}

it('adds a permission the role does not give', function () {
    $admin = panelUser(Role::Admin);
    $operator = panelUser(Role::Operator);

    $this->actingAs($admin)->put(route('panel.users.overrides', overridesMembershipOf($operator)), ['allow' => ['registry.manage']])->assertSessionHas('success');

    $operator = $operator->fresh();
    expect(Gate::forUser($operator)->allows('manage-registry'))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('operate-games'))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('manage-competitions'))->toBeFalse();
});

it('removes a permission the role gives, and deny wins over allow', function () {
    $admin = panelUser(Role::Admin);
    $organizer = panelUser(Role::Organizer);

    $this->actingAs($admin)->put(route('panel.users.overrides', overridesMembershipOf($organizer)), ['allow' => ['games.operate'], 'deny' => ['games.operate', 'registry.manage']])->assertSessionHas('success');

    $organizer = $organizer->fresh();
    expect(Gate::forUser($organizer)->allows('operate-games'))->toBeFalse()
        ->and(Gate::forUser($organizer)->allows('manage-registry'))->toBeFalse()
        ->and(Gate::forUser($organizer)->allows('manage-competitions'))->toBeTrue();
});

it('drops exceptions that change nothing and replaces the previous ones', function () {
    $admin = panelUser(Role::Admin);
    $operator = panelUser(Role::Operator);
    $membership = overridesMembershipOf($operator);

    // operate-games já vem do papel (allow inútil); competitions.manage não vem (deny inútil).
    $this->actingAs($admin)->put(route('panel.users.overrides', $membership), ['allow' => ['games.operate', 'registry.manage'], 'deny' => ['competitions.manage']]);
    expect($membership->overrides()->get()->map(fn ($o) => "{$o->permission}:{$o->effect}")->all())->toBe(['registry.manage:allow']);

    $this->actingAs($admin)->put(route('panel.users.overrides', $membership), []);
    expect($membership->overrides()->count())->toBe(0);
});

it('refuses to take user management from the last manager', function () {
    $admin = panelUser(Role::Admin);

    $this->actingAs($admin)->put(route('panel.users.overrides', overridesMembershipOf($admin)), ['deny' => ['users.manage']])->assertSessionHas('error');

    expect(overridesMembershipOf($admin)->overrides()->count())->toBe(0)
        ->and(Gate::forUser($admin->fresh())->allows('manage-users'))->toBeTrue();

    // Com outro administrador, pode.
    panelUser(Role::Admin);
    $this->actingAs($admin)->put(route('panel.users.overrides', overridesMembershipOf($admin)), ['deny' => ['users.manage']])->assertSessionHas('success');
});

it('lets an exception grant user management, which then counts for the guard', function () {
    $admin = panelUser(Role::Admin);
    $organizer = panelUser(Role::Organizer);
    $this->actingAs($admin)->put(route('panel.users.overrides', overridesMembershipOf($organizer)), ['allow' => ['users.manage']])->assertSessionHas('success');

    // O administrador original agora pode sair, pois o organizador também gerencia usuários.
    $this->actingAs($admin)->delete(route('panel.users.destroy', overridesMembershipOf($admin)))->assertSessionHas('success');
});

it('rejects unknown permissions and is for user managers only', function () {
    $admin = panelUser(Role::Admin);
    $operator = panelUser(Role::Operator);
    $membership = overridesMembershipOf($operator);

    $this->actingAs($admin)->put(route('panel.users.overrides', $membership), ['allow' => ['x.y']])->assertSessionHasErrors('allow.0');
    $this->actingAs($operator)->put(route('panel.users.overrides', $membership), ['allow' => ['users.manage']])->assertForbidden();
});

it('never gives panel permissions to a team manager through an exception', function () {
    $membership = Membership::query()->create(['user_id' => User::factory()->create()->getKey(), 'role' => Role::TeamManager]);
    $membership->overrides()->create(['permission' => Permission::GamesOperate->value, 'effect' => 'allow']);

    expect($membership->fresh(['overrides'])->permissions())->toBe([]);
});

it('records who changed what in the audit trail', function () {
    $admin = panelUser(Role::Admin);
    $operator = panelUser(Role::Operator);
    $membership = overridesMembershipOf($operator);

    $this->actingAs($admin)->put(route('panel.users.overrides', $membership), ['allow' => ['registry.manage']]);
    $this->actingAs($admin)->patch(route('panel.users.update', $membership), ['role' => 'viewer']);
    $this->actingAs($admin)->post(route('panel.profiles.store'), ['name' => 'Sob medida', 'permissions' => ['games.operate']]);

    $overrides = AccessAudit::query()->where('action', 'member.overrides')->firstOrFail();
    expect($overrides->actor_id)->toEqual($admin->getKey())
        ->and($overrides->subject)->toBe($operator->name)
        ->and($overrides->summary)->toContain('Passou a ter');
    expect(AccessAudit::query()->where('action', 'member.access')->exists())->toBeTrue()
        ->and(AccessAudit::query()->where('action', 'profile.created')->exists())->toBeTrue();
});

it('shows the audit trail to user managers only', function () {
    $admin = panelUser(Role::Admin);
    $this->actingAs($admin)->put(route('panel.users.overrides', overridesMembershipOf(panelUser(Role::Operator))), ['allow' => ['registry.manage']]);

    $this->actingAs($admin)->get(route('panel.access-audit.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Panel/AccessAudit/Index')->has('entries', 1));

    $this->actingAs(panelUser(Role::Organizer))->get(route('panel.access-audit.index'))->assertForbidden();
});

it('lists base permissions and exceptions on the users screen', function () {
    $admin = panelUser(Role::Admin);
    $operator = panelUser(Role::Operator);
    $this->actingAs($admin)->put(route('panel.users.overrides', overridesMembershipOf($operator)), ['allow' => ['registry.manage']]);

    $this->actingAs($admin)->get(route('panel.users.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Panel/Users/Index')->has('catalog')
            ->where('members', fn ($members) => collect($members)->contains(fn ($m) => $m['email'] === $operator->email && $m['allow'] == ['registry.manage'] && $m['base'] == ['games.operate'])));
});

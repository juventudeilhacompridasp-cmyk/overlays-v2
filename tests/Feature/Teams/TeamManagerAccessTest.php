<?php

use App\Models\User;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Mail\TeamManagerInvitation;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamManager;
use App\Modules\Teams\Support\ManagedTeams;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Mail::fake();
});

describe('login and routing by role', function () {
    it('sends the manager to the manager area and everyone else to the panel', function () {
        $team = Team::factory()->create();
        $manager = managerFor($team);

        $this->actingAs($manager)->get('/dashboard')->assertRedirect(route('manager.dashboard'));
        $this->actingAs(panelUser(Role::Organizer))->get('/dashboard')->assertRedirect(route('panel.dashboard'));
    });

    it('keeps the manager out of the organizer panel, even to read', function () {
        $manager = managerFor(Team::factory()->create());

        $this->actingAs($manager)->get(route('panel.dashboard'))->assertForbidden();
        $this->actingAs($manager)->get(route('panel.teams.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('panel.users.index'))->assertForbidden();
    });

    it('keeps the panel roles out of the manager area', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('manager.dashboard'))->assertForbidden();
        $this->actingAs(panelUser(Role::Viewer))->get(route('manager.games.index'))->assertForbidden();
    });

    it('requires login', function () {
        $this->get(route('manager.dashboard'))->assertRedirect(route('login'));
    });

    it('has no ability at all', function () {
        expect(Role::TeamManager->abilities())->toBe([])
            ->and(Role::TeamManager->usesPanel())->toBeFalse();
    });

    it('needs the team portal feature', function () {
        $organization = CurrentOrganization::get();
        $organization->update(['plan' => Plan::Basic->value, 'features' => null]);
        CurrentOrganization::set($organization->fresh());

        $this->actingAs(managerFor(Team::factory()->create()))->get(route('manager.dashboard'))->assertForbidden();
    });
});

describe('invitation by the organizer', function () {
    it('creates the account, links the team and mails a password link', function () {
        $team = Team::factory()->create();

        $this->actingAs(panelUser(Role::Organizer))
            ->post(route('panel.teams.managers.store', $team), ['name' => 'Rita Gestora', 'email' => 'Rita@Exemplo.com'])
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'rita@exemplo.com')->firstOrFail();

        expect($user->email_verified_at)->not->toBeNull()
            ->and(Membership::query()->where('user_id', $user->id)->value('role'))->toBe(Role::TeamManager)
            ->and(TeamManager::query()->where('user_id', $user->id)->where('team_id', $team->id)->exists())->toBeTrue();

        Mail::assertQueued(TeamManagerInvitation::class, fn (TeamManagerInvitation $mail) => $mail->hasTo('rita@exemplo.com')
            && $mail->newAccount
            && str_contains($mail->link, '/reset-password/')
            && str_contains($mail->link, urlencode('rita@exemplo.com')));
    });

    it('reuses an existing account, which only gets the link and a notice', function () {
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $manager = managerFor($other);

        $this->actingAs(panelUser(Role::Organizer))
            ->post(route('panel.teams.managers.store', $team), ['name' => $manager->name, 'email' => $manager->email])
            ->assertSessionHas('success');

        expect(ManagedIds($manager))->toEqualCanonicalizing([$team->id, $other->id])
            ->and(Membership::query()->where('user_id', $manager->id)->count())->toBe(1);

        Mail::assertQueued(TeamManagerInvitation::class, fn (TeamManagerInvitation $mail) => ! $mail->newAccount && str_ends_with($mail->link, '/gestor'));
    });

    it('refuses an e-mail that already has a panel role in the organization', function () {
        $team = Team::factory()->create();
        $staff = panelUser(Role::Organizer);

        $this->actingAs(panelUser(Role::Admin))
            ->post(route('panel.teams.managers.store', $team), ['name' => 'x', 'email' => $staff->email])
            ->assertSessionHas('error');

        expect(TeamManager::query()->count())->toBe(0);
        Mail::assertNothingQueued();
    });

    it('is not duplicated when invited twice for the same team', function () {
        $team = Team::factory()->create();
        $organizer = panelUser(Role::Organizer);

        foreach ([1, 2] as $_) {
            $this->actingAs($organizer)->post(route('panel.teams.managers.store', $team), ['name' => 'Rita', 'email' => 'rita@exemplo.com']);
        }

        expect(TeamManager::query()->count())->toBe(1);
    });

    it('lets only people who manage the registry invite', function () {
        $team = Team::factory()->create();

        foreach ([Role::Operator, Role::Viewer] as $role) {
            $this->actingAs(panelUser($role))
                ->post(route('panel.teams.managers.store', $team), ['name' => 'Rita', 'email' => 'rita@exemplo.com'])
                ->assertSessionHas('error');
        }

        expect(TeamManager::query()->count())->toBe(0);
    });

    it('does not count managers in the user limit of the package', function () {
        $organization = CurrentOrganization::get();
        $organization->update(['limits' => ['max_users' => 2]]);
        CurrentOrganization::set($organization->fresh());

        managerFor(Team::factory()->create());
        managerFor(Team::factory()->create());
        $admin = panelUser(Role::Admin);

        // Dois gestores + 1 administrador: só o administrador conta, então ainda cabe mais um da equipe interna...
        $this->actingAs($admin)
            ->post(route('panel.users.store'), ['name' => 'Novo', 'email' => 'novo@exemplo.com', 'password' => 'senha-segura-1', 'role' => 'organizer'])
            ->assertSessionHas('success');

        // ...e o próximo já passa do limite de 2.
        $this->actingAs($admin)
            ->post(route('panel.users.store'), ['name' => 'Outro', 'email' => 'outro@exemplo.com', 'password' => 'senha-segura-1', 'role' => 'organizer'])
            ->assertSessionHas('error');
    });

    it('resends the invitation and removes the manager, dropping the role with the last team', function () {
        $team = Team::factory()->create();
        $manager = managerFor($team);
        $link = TeamManager::query()->firstOrFail();
        $organizer = panelUser(Role::Organizer);

        $this->actingAs($organizer)->post(route('panel.teams.managers.resend', $link))->assertSessionHas('success');
        Mail::assertQueued(TeamManagerInvitation::class);

        $this->actingAs($organizer)->delete(route('panel.teams.managers.destroy', $link))->assertSessionHas('success');

        expect(TeamManager::query()->count())->toBe(0)
            ->and(Membership::query()->where('user_id', $manager->id)->exists())->toBeFalse();
    });

    it('keeps the role while the manager still has another team', function () {
        $first = Team::factory()->create();
        $second = Team::factory()->create();
        $manager = managerFor($first);
        managerFor($second, $manager);

        $this->actingAs(panelUser(Role::Organizer))->delete(route('panel.teams.managers.destroy', TeamManager::query()->where('team_id', $first->id)->firstOrFail()));

        expect(Membership::query()->where('user_id', $manager->id)->exists())->toBeTrue();
    });

    it('does not show managers in the users screen nor allow changing their role there', function () {
        $manager = managerFor(Team::factory()->create());
        $admin = panelUser(Role::Admin);
        $membership = Membership::query()->where('user_id', $manager->id)->firstOrFail();

        $this->actingAs($admin)->get(route('panel.users.index'))
            ->assertInertia(fn ($page) => $page->has('members', 1)->where('roles', fn ($roles) => collect($roles)->pluck('value')->doesntContain('manager')));

        $this->actingAs($admin)->patch(route('panel.users.update', $membership), ['role' => 'admin'])->assertNotFound();
        $this->actingAs($admin)->delete(route('panel.users.destroy', $membership))->assertNotFound();
        $this->actingAs($admin)->post(route('panel.users.store'), ['name' => 'x', 'email' => 'x@exemplo.com', 'password' => 'senha-segura-1', 'role' => 'manager'])
            ->assertSessionHasErrors('role');
    });

    it('hides the managers section when the package lacks the feature', function () {
        $organization = CurrentOrganization::get();
        $organization->update(['plan' => Plan::Basic->value, 'features' => null]);
        CurrentOrganization::set($organization->fresh());
        $team = Team::factory()->create();

        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.teams.edit', $team))
            ->assertInertia(fn ($page) => $page->where('managers', null));
        $this->actingAs(panelUser(Role::Organizer))
            ->post(route('panel.teams.managers.store', $team), ['name' => 'Rita', 'email' => 'rita@exemplo.com'])
            ->assertSessionHas('error');
    });
});

describe('what the manager sees', function () {
    /** @return array{team: Team, entry: Entry, game: Game, manager: User} */
    function managedSetup(): array
    {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($stage);
        $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();

        return ['team' => $entries[0]->team, 'entry' => $entries[0], 'game' => $game, 'manager' => managerFor($entries[0]->team)];
    }

    it('lists only the games of the linked teams', function () {
        ['manager' => $manager, 'game' => $game] = managedSetup();
        ['stage' => $otherStage] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($otherStage);

        $this->actingAs($manager)->get(route('manager.games.index', ['filtro' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('games.data', 1)->where('games.data.0.id', $game->id));
    });

    it('shows the dashboard with the manager teams, entries and next games', function () {
        ['manager' => $manager, 'team' => $team, 'game' => $game] = managedSetup();

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('teams', 1)->where('teams.0.id', $team->id)
                ->has('entries', 1)
                ->has('upcoming', 1)->where('upcoming.0.id', $game->id));
    });

    it('shows the own roster with discipline but never CPF or documents', function () {
        ['manager' => $manager, 'entry' => $entry] = managedSetup();
        RosterEntry::factory()->count(3)->sequence(fn ($s) => ['shirt_number' => $s->index + 1])->create(['entry_id' => $entry->id]);

        $response = $this->actingAs($manager)->get(route('manager.entries.show', $entry))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('roster', 3)->has('roster.0', fn ($row) => $row->has('suspended_games')->missing('document_hash')->missing('cpf')->etc()));

        $props = $response->viewData('page')['props'];
        unset($props['ziggy']);
        expect(json_encode($props))->not->toContain('document_hash')->not->toContain('cpf');
    });

    it('hides other teams: entry, game and roster answer 404', function () {
        ['manager' => $manager] = managedSetup();
        ['entries' => $others, 'stage' => $stage] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($stage);
        $foreignGame = Game::query()->where('stage_id', $stage->id)->firstOrFail();

        $this->actingAs($manager)->get(route('manager.entries.show', $others[0]))->assertNotFound();
        $this->actingAs($manager)->get(route('manager.games.show', $foreignGame))->assertNotFound();
    });

    it('does not leak across organizations', function () {
        ['manager' => $manager, 'entry' => $entry] = managedSetup();

        $outsider = Organization::factory()->create(['slug' => 'outra']);
        $foreign = CurrentOrganization::using($outsider, fn () => Entry::factory()->create());

        $this->actingAs($manager)->get(route('manager.entries.show', $foreign))->assertNotFound();
        expect($entry->exists)->toBeTrue();
    });

    it('does not show games when the opponent slot is still open', function () {
        ['manager' => $manager, 'game' => $game] = managedSetup();
        $game->update(['status' => GameStatus::Scheduled]);

        $this->actingAs($manager)->get(route('manager.games.show', $game))->assertOk()
            ->assertInertia(fn ($page) => $page->where('game.status', 'scheduled'));
    });
});

function ManagedIds(User $user): array
{
    return ManagedTeams::ids($user);
}

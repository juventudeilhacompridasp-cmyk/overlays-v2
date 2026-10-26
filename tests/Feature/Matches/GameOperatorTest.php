<?php

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameOfficial;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\ActivityLog;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

describe('the mesário-only profile', function () {
    it('is recognised only for who operates games and has no other permission', function () {
        expect(panelUser(Role::Operator)->isOperatorOnly())->toBeTrue()
            ->and(panelUser(Role::Organizer)->isOperatorOnly())->toBeFalse()
            ->and(panelUser(Role::Admin)->isOperatorOnly())->toBeFalse()
            ->and(panelUser(Role::Viewer)->isOperatorOnly())->toBeFalse();
    });

    it('goes straight to the mesário app after login, while the others keep the dashboard', function () {
        $this->actingAs(panelUser(Role::Operator))->get('/dashboard')->assertRedirect(route('panel.mesario'));
        $this->actingAs(panelUser(Role::Organizer))->get('/dashboard')->assertRedirect(route('panel.dashboard'));
        $this->actingAs(panelUser(Role::Viewer))->get('/dashboard')->assertRedirect(route('panel.dashboard'));
    });

    it('still lets the server decide: the operator keeps reading but cannot write outside the games area', function () {
        $operator = panelUser(Role::Operator);

        $this->actingAs($operator)->get(route('panel.mesario.games'))->assertOk();
        $this->actingAs($operator)->get(route('panel.teams.create'))->assertForbidden();
        $this->actingAs($operator)->get(route('panel.users.index'))->assertForbidden();
    });
});

describe('the championship games list', function () {
    it('lists every game, not only the mesário window, and is readable by any member', function () {
        ['stage' => $stage] = fixtureDivision(4);
        app(\App\Modules\Matches\Actions\GenerateStageFixtures::class)->handle($stage);
        $games = Game::query()->where('stage_id', $stage->id)->orderBy('id')->get();
        $games[0]->update(['scheduled_at' => now()->addDays(40)]);
        $games[1]->update(['status' => GameStatus::Finished, 'finished_at' => now()->subDays(30), 'home_score' => 2, 'away_score' => 0]);

        foreach ([Role::Viewer, Role::Operator, Role::Admin] as $role) {
            $this->actingAs(panelUser($role))->get(route('panel.mesario.games'))->assertOk()->assertInertia(fn ($page) => $page
                ->component('Panel/Mesario/Games')
                ->where('games.total', $games->count())
                ->has('statuses')->has('competitions'));
        }

        // A janela do app do mesário continua igual: o jogo daqui a 40 dias não entra.
        $this->actingAs(panelUser(Role::Operator))->get(route('panel.mesario'))->assertInertia(fn ($page) => $page->where('games', fn ($list) => ! collect($list)->pluck('id')->contains($games[0]->id)));
    });

    it('filters by status and competition and searches by team', function () {
        ['stage' => $stage] = fixtureDivision(4);
        app(\App\Modules\Matches\Actions\GenerateStageFixtures::class)->handle($stage);
        $games = Game::query()->where('stage_id', $stage->id)->with('homeEntry.team')->orderBy('id')->get();
        $games[0]->update(['status' => GameStatus::Finished]);
        $admin = panelUser(Role::Admin);
        $competition = $games[0]->division->season->competition_id;

        $this->actingAs($admin)->get(route('panel.mesario.games', ['filter' => ['status' => 'finished']]))->assertInertia(fn ($page) => $page->where('games.total', 1));
        $this->actingAs($admin)->get(route('panel.mesario.games', ['filter' => ['competition' => $competition]]))->assertInertia(fn ($page) => $page->where('games.total', $games->count()));
        $this->actingAs($admin)->get(route('panel.mesario.games', ['filter' => ['competition' => 'nao-existe']]))->assertInertia(fn ($page) => $page->where('games.total', 0));
        $this->actingAs($admin)->get(route('panel.mesario.games', ['q' => $games[0]->homeEntry->team->name]))->assertInertia(fn ($page) => $page->where('games.total', fn ($total) => $total >= 1));
    });

    it('is closed to guests and ignores invalid filters', function () {
        $this->get(route('panel.mesario.games'))->assertRedirect('/login');
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.mesario.games', ['filter' => ['status' => 'x; drop'], 'sort' => 'password', 'per_page' => 9999]))->assertOk();
    });
});

describe('the match operator screen', function () {
    it('shows the state of the game, lineups, officials, last events and the recent changes', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        GameOfficial::factory()->create(['game_id' => $game->id]);
        $operator = panelUser(Role::Operator);
        $this->actingAs($operator)->post(route('panel.games.events.store', $game), ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1, 'minute' => 12]);

        $this->actingAs($operator)->get(route('panel.games.operator', $game))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Panel/Games/Operator')
            ->where('game.id', $game->id)
            ->where('game.home.starters', 11)
            ->where('game.home.bench', 3)
            ->where('game.home_score', 1)
            ->has('officials', 1)
            ->where('events.0.label', 'Gol')
            ->where('events.0.minute', 12)
            ->where('history', fn ($history) => collect($history)->contains(fn ($entry) => $entry['actor'] === $operator->name))
            ->where('links.activity', null));
    });

    it('offers the full activity log only to who manages users', function () {
        ['game' => $game] = sheetGame();

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.games.operator', $game))->assertInertia(fn ($page) => $page->where('links.activity', route('panel.activity-log.index', ['game' => $game->id])));
        $this->actingAs(panelUser(Role::Operator))->get(route('panel.games.operator', $game))->assertInertia(fn ($page) => $page->where('links.activity', null));
    });

    it('limits or hides the recent changes by the organization parameter', function () {
        $game = Game::factory()->create();
        foreach (range(1, 5) as $i) {
            ActivityLog::query()->create(['actor_name' => "Pessoa {$i}", 'area' => 'panel', 'kind' => 'request', 'action' => 'x', 'summary' => "Ação {$i}", 'outcome' => 'ok', 'game_id' => $game->id]);
        }
        $admin = panelUser(Role::Admin);

        CurrentOrganization::get()->update(['settings' => ['mesario' => ['history' => 3]]]);
        $this->actingAs($admin)->get(route('panel.games.operator', $game))->assertInertia(fn ($page) => $page->has('history', 3));

        CurrentOrganization::get()->update(['settings' => ['mesario' => ['history' => 0]]]);
        $this->actingAs($admin)->get(route('panel.games.operator', $game))->assertInertia(fn ($page) => $page->has('history', 0));
    });

    it('is closed to guests', function () {
        $this->get(route('panel.games.operator', Game::factory()->create()))->assertRedirect('/login');
    });
});

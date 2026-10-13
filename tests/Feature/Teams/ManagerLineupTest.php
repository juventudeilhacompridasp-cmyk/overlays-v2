<?php

use App\Models\User;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Actions\SubmitTeamLineup;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Mail::fake();
});

const SLOTS_442 = ['gk', 'd1', 'd2', 'd3', 'd4', 'm1', 'm2', 'm3', 'm4', 'f1', 'f2'];

/**
 * Jogo agendado para daqui a 2 dias, com elenco de 14 atletas no mandante e o gestor dele.
 *
 * @return array{game: Game, home: Entry, away: Entry, hp: list<string>, ap: list<string>, manager: User}
 */
function managedGame(): array
{
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);

    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    $game->update(['scheduled_at' => now()->addDays(2)]);

    $players = fn (Entry $entry): array => RosterEntry::factory()->count(14)->sequence(fn ($s) => ['shirt_number' => $s->index + 1])
        ->create(['entry_id' => $entry->id])->pluck('person_id')->all();

    $hp = $players($game->homeEntry);
    $ap = $players($game->awayEntry);

    return ['game' => $game->fresh(), 'home' => $game->homeEntry, 'away' => $game->awayEntry, 'hp' => $hp, 'ap' => $ap, 'manager' => managerFor($game->homeEntry->team)];
}

/** Corpo da requisição com 11 titulares no 4-4-2, 3 reservas e o primeiro como capitão. */
function lineupPayload(array $hp, array $override = []): array
{
    $starters = array_slice($hp, 0, 11);

    return array_replace([
        'formation' => '4-4-2',
        'starters' => $starters,
        'bench' => array_slice($hp, 11, 3),
        'staff' => [],
        'positions' => array_combine($starters, SLOTS_442),
        'captain' => $starters[1],
        'notes' => null,
        'confirm' => true,
    ], $override);
}

describe('saving the lineup', function () {
    it('confirms a complete lineup with positions and captain', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp, 'manager' => $manager] = managedGame();

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, ['notes' => '  Pressionar a saída de bola.  ']))
            ->assertSessionHas('success');

        $rows = GameLineup::query()->where('game_id', $game->id)->where('entry_id', $home->id)->get();
        $tactic = GameTactic::query()->where('game_id', $game->id)->where('entry_id', $home->id)->firstOrFail();

        expect($rows)->toHaveCount(14)
            ->and($rows->firstWhere('person_id', $hp[0])->position)->toBe('gk')
            ->and($rows->firstWhere('person_id', $hp[1])->is_captain)->toBeTrue()
            ->and($rows->firstWhere('person_id', $hp[0])->sort)->toBe(0)
            ->and($tactic->formation)->toBe('4-4-2')
            ->and($tactic->notes)->toBe('Pressionar a saída de bola.')
            ->and($tactic->isConfirmed())->toBeTrue()
            ->and($tactic->confirmed_by)->toBe($manager->id);
    });

    it('keeps a partial lineup as a draft and refuses to confirm it', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp, 'manager' => $manager] = managedGame();

        $starters = array_slice($hp, 0, 3);
        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, [
            'starters' => $starters, 'bench' => [], 'positions' => array_combine($starters, ['gk', 'd1', 'd2']), 'captain' => null, 'confirm' => false,
        ]))->assertSessionHas('success');

        expect(GameTactic::query()->where('game_id', $game->id)->firstOrFail()->isConfirmed())->toBeFalse()
            ->and(GameLineup::query()->where('game_id', $game->id)->where('entry_id', $home->id)->count())->toBe(3);

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, [
            'starters' => $starters, 'bench' => [], 'positions' => array_combine($starters, ['gk', 'd1', 'd2']), 'captain' => $starters[0], 'confirm' => true,
        ]))->assertSessionHas('error');
    });

    it('requires a captain to confirm', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, ['captain' => null]))
            ->assertSessionHas('error');

        expect(GameTactic::query()->count())->toBe(0);
    });

    it('rejects a captain who is not in the lineup', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, ['captain' => $hp[13], 'bench' => [$hp[11]]]))
            ->assertSessionHas('error');
    });

    it('rejects people from outside the roster, such as athletes of the opponent', function () {
        ['game' => $game, 'hp' => $hp, 'ap' => $ap, 'manager' => $manager] = managedGame();

        $starters = [...array_slice($hp, 0, 10), $ap[0]];
        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, ['starters' => $starters, 'positions' => array_combine($starters, SLOTS_442)]))
            ->assertSessionHas('error');

        expect(GameLineup::query()->count())->toBe(0);
    });

    it('rejects a suspended athlete', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $earlier = Game::factory()->create(['stage_id' => $game->stage_id, 'home_entry_id' => $home->id, 'round' => 0]);
        app(SaveGameLineup::class)->handle($earlier, $home, [$hp[0]]);
        app(RecordGameEvent::class)->handle($earlier, GameEventType::RedCard, $home->id, $hp[0]);
        app(App\Modules\Matches\Actions\FinishGame::class)->handle($earlier);

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp))->assertSessionHas('error');
    });

    it('validates the formation and the positions', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $starters = array_slice($hp, 0, 11);

        $cases = [
            'unknown formation' => ['formation' => '9-9-9'],
            'formation of another sport' => ['formation' => 'quinteto'],
            'unknown slot' => ['positions' => array_combine($starters, [...array_slice(SLOTS_442, 0, 10), 'zz'])],
            'repeated slot' => ['positions' => array_combine($starters, [...array_slice(SLOTS_442, 0, 10), 'gk'])],
        ];

        foreach ($cases as $name => $override) {
            $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, $override))
                ->assertSessionHas('error');
        }

        expect(GameLineup::query()->count())->toBe(0);
    });

    it('limits the number of starters by the regulation', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();

        $starters = array_slice($hp, 0, 12);
        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, [
            'starters' => $starters, 'bench' => [], 'positions' => array_combine(array_slice($starters, 0, 11), SLOTS_442), 'confirm' => false,
        ]))->assertSessionHas('error');
    });
});

describe('the manager window', function () {
    it('closes after the deadline of the regulation', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $game->update(['scheduled_at' => now()->addMinutes(10)]); // prazo padrão: 30 min antes

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp))
            ->assertSessionHas('error');

        $this->actingAs($manager)->get(route('manager.games.show', $game))
            ->assertInertia(fn ($page) => $page->where('window.closed_reason', fn ($reason) => str_contains($reason, 'prazo')));
    });

    it('lets the deadline be a regulation parameter', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $game->update(['scheduled_at' => now()->addMinutes(10)]);
        $game->division->update(['rules' => ['lineup' => ['manager_deadline_minutes' => 0]]]);

        $this->actingAs($manager)->put(route('manager.games.lineup', $game->fresh()), lineupPayload($hp))
            ->assertSessionHas('success');
    });

    it('closes once the game has started', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $game->update(['status' => GameStatus::InProgress]);

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp))->assertSessionHas('error');
    });

    it('closes after the first event of the team on the sheet', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp, 'manager' => $manager] = managedGame();
        app(SaveGameLineup::class)->handle($game, $home, array_slice($hp, 0, 11));
        app(RecordGameEvent::class)->handle($game, GameEventType::YellowCard, $home->id, $hp[0]);

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp))->assertSessionHas('error');
    });
});

describe('isolation', function () {
    it('answers 404 to a manager of another team, for the game and for saving', function () {
        ['game' => $game, 'hp' => $hp] = managedGame();
        $stranger = managerFor(App\Modules\Teams\Models\Team::factory()->create());

        $this->actingAs($stranger)->get(route('manager.games.show', $game))->assertNotFound();
        $this->actingAs($stranger)->put(route('manager.games.lineup', $game), lineupPayload($hp))->assertNotFound();

        expect(GameLineup::query()->count())->toBe(0);
    });

    it('lets each manager touch only the lineup of his own side', function () {
        ['game' => $game, 'home' => $home, 'away' => $away, 'hp' => $hp, 'ap' => $ap] = managedGame();
        $awayManager = managerFor($away->team);

        $this->actingAs($awayManager)->put(route('manager.games.lineup', $game), lineupPayload($ap))->assertSessionHas('success');
        // Mandar os atletas do mandante pelo gestor do visitante não passa: eles não são do elenco dele.
        $this->actingAs($awayManager)->put(route('manager.games.lineup', $game), lineupPayload($hp))->assertSessionHas('error');

        expect(GameLineup::query()->where('entry_id', $home->id)->count())->toBe(0)
            ->and(GameLineup::query()->where('entry_id', $away->id)->count())->toBe(14);
    });

    it('does not let the panel staff in through the manager route', function () {
        ['game' => $game, 'hp' => $hp] = managedGame();

        $this->actingAs(panelUser(Role::Organizer))->put(route('manager.games.lineup', $game), lineupPayload($hp))->assertForbidden();
    });
});

describe('interaction with the sheet', function () {
    it('keeps positions and captain when the scorer saves only the ids again', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp));

        app(SaveGameLineup::class)->handle($game->fresh(), $home, array_slice($hp, 0, 11), array_slice($hp, 11, 3));

        $row = GameLineup::query()->where('game_id', $game->id)->where('person_id', $hp[1])->firstOrFail();
        expect($row->position)->toBe('d1')
            ->and($row->is_captain)->toBeTrue()
            ->and(GameTactic::query()->firstOrFail()->isConfirmed())->toBeTrue();
    });

    it('drops the confirmation when the scorer changes who plays', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp));

        $starters = [...array_slice($hp, 0, 10), $hp[11]];
        app(SaveGameLineup::class)->handle($game->fresh(), $home, $starters, [$hp[10]]);

        $moved = GameLineup::query()->where('game_id', $game->id)->where('person_id', $hp[10])->firstOrFail();
        expect(GameTactic::query()->firstOrFail()->isConfirmed())->toBeFalse()
            ->and($moved->position)->toBeNull();
    });

    it('can be submitted directly by the action, without HTTP', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $starters = array_slice($hp, 0, 11);

        $tactic = app(SubmitTeamLineup::class)->handle($game, $home, $manager, '4-4-2', $starters, [], [], array_combine($starters, SLOTS_442), $starters[0], null, true);

        expect($tactic->isConfirmed())->toBeTrue();
    });
});

describe('what is shown', function () {
    it('shows the editor data, with the formations of the sport and the previous lineup to repeat', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();

        $this->actingAs($manager)->get(route('manager.games.show', $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sport', 'football')
                ->has('formations', 8)
                ->has('roster', 14)
                ->where('game.lineup', 'missing')
                ->where('previous', null)
                ->where('opponent', null));

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp));

        ['stage' => $stage] = ['stage' => $game->stage];
        $next = Game::factory()->create(['stage_id' => $stage->id, 'home_entry_id' => $game->home_entry_id, 'away_entry_id' => $game->away_entry_id, 'round' => 9, 'scheduled_at' => now()->addDays(5)]);

        $this->actingAs($manager)->get(route('manager.games.show', $next))
            ->assertInertia(fn ($page) => $page->where('previous.formation', '4-4-2')->has('previous.starters', 11));
    });

    it('shows the lineup status on the games list', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();

        $this->actingAs($manager)->get(route('manager.games.index'))->assertInertia(fn ($page) => $page->where('games.data.0.lineup', 'missing'));

        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp));
        $this->actingAs($manager)->get(route('manager.games.index'))->assertInertia(fn ($page) => $page->where('games.data.0.lineup', 'confirmed'));
    });

    it('shows the opponent lineup only after kickoff and with public names only', function () {
        ['game' => $game, 'away' => $away, 'hp' => $hp, 'ap' => $ap, 'manager' => $manager] = managedGame();
        $awayManager = managerFor($away->team);

        // Dois nomes do adversário: um maior de idade (apelido) e um menor (primeiro nome + inicial).
        Person::query()->whereKey($ap[0])->update(['name' => 'Carlos Alberto Silva', 'nickname' => 'Carlão', 'birth_date' => now()->subYears(30)]);
        Person::query()->whereKey($ap[1])->update(['name' => 'Joaquim Santos Pereira', 'nickname' => 'Quinzinho', 'birth_date' => now()->subYears(15)]);
        $this->actingAs($awayManager)->put(route('manager.games.lineup', $game), lineupPayload($ap, ['notes' => 'segredo-tático-xyz']));

        $this->actingAs($manager)->get(route('manager.games.show', $game))->assertInertia(fn ($page) => $page->where('opponent', null));

        $game->update(['status' => GameStatus::InProgress]);

        $response = $this->actingAs($manager)->get(route('manager.games.show', $game))->assertOk();
        $props = $response->viewData('page')['props'];
        unset($props['ziggy']);
        $json = json_encode($props);

        expect($props['opponent']['starters'][0]['name'])->toBe('Carlão')
            ->and($props['opponent']['starters'][1]['name'])->toBe('Joaquim P.')
            ->and($props['opponent']['formation'])->toBe('4-4-2')
            ->and($json)->not->toContain('segredo-tático-xyz')->not->toContain('Quinzinho')->not->toContain('Joaquim Santos');
    });
});

describe('overlay and sheet', function () {
    it('puts the formation, the position and the captain in the lineup overlay but never the private notes', function () {
        ['game' => $game, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp, ['notes' => 'segredo-tático-xyz']));

        $channel = OverlayChannel::query()->create(['name' => 'Canal', 'token' => OverlayChannel::newToken(), 'game_id' => $game->id, 'layers' => ['lineup' => true]]);
        $state = OverlayState::for($channel);

        $starters = collect($state['lineup']['home']['starters']);

        expect($state['lineup']['home']['formation'])->toBe('4-4-2')
            ->and($starters->firstWhere('captain', true))->not->toBeNull()
            ->and($starters->pluck('position')->filter()->all())->toContain('GOL')
            ->and(json_encode($state))->not->toContain('segredo-tático-xyz');
    });

    it('tells the scorer that the manager confirmed the lineup', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp, 'manager' => $manager] = managedGame();
        $this->actingAs($manager)->put(route('manager.games.lineup', $game), lineupPayload($hp));

        $this->actingAs(panelUser(Role::Operator))->get(route('panel.games.sheet', $game))
            ->assertInertia(fn ($page) => $page->where('home.tactic.confirmed', true)->where('home.tactic.formation', '4-4-2'));

        expect($home->exists)->toBeTrue();
    });
});

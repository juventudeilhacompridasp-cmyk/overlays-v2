<?php

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Enums\Sport;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Actions\ReopenGame;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Actions\VoidGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** @return array{game: Game, hp: list<string>, ap: list<string>} 5 titulares e 7 reservas por equipe */
function basketballGame(int $teams = 2): array
{
    ['stage' => $stage] = fixtureDivision($teams);
    $stage->division->season->competition->update(['sport' => Sport::Basketball]);
    app(GenerateStageFixtures::class)->handle($stage);

    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    $players = fn ($entry) => RosterEntry::factory()->count(12)->sequence(fn ($s) => ['shirt_number' => $s->index + 4])->create(['entry_id' => $entry->id])->pluck('person_id')->all();
    $hp = $players($game->homeEntry);
    $ap = $players($game->awayEntry);

    app(SaveGameLineup::class)->handle($game, $game->homeEntry, array_slice($hp, 0, 5), array_slice($hp, 5));
    app(SaveGameLineup::class)->handle($game, $game->awayEntry, array_slice($ap, 0, 5), array_slice($ap, 5));

    return ['game' => $game->fresh(), 'hp' => $hp, 'ap' => $ap];
}

function basket(Game $game, GameEventType $type, string $side, string $person, int $period = 1): void
{
    app(RecordGameEvent::class)->handle($game->fresh(), $type, $side === 'home' ? $game->home_entry_id : $game->away_entry_id, $person, null, $period);
}

it('adds up free throws, field goals and threes, with the score per quarter', function () {
    ['game' => $game, 'hp' => $hp, 'ap' => $ap] = basketballGame();

    basket($game, GameEventType::FieldGoal, 'home', $hp[0]);
    basket($game, GameEventType::ThreePointer, 'home', $hp[1]);
    basket($game, GameEventType::FreeThrow, 'away', $ap[0]);
    basket($game, GameEventType::FieldGoal, 'away', $ap[1], 2);

    $game->refresh();
    $snapshot = PublicGameSnapshot::for($game);

    expect($game->home_score)->toBe(5)->and($game->away_score)->toBe(3)->and($game->status)->toBe(GameStatus::InProgress)
        ->and($snapshot['score_model'])->toBe('points')
        ->and($snapshot['quarters'])->toBe([['period' => 1, 'home' => 5, 'away' => 1], ['period' => 2, 'home' => 0, 'away' => 2]])
        ->and($snapshot['sets'])->toBeNull();
});

it('only lets players on court score, and no goals or rally points', function () {
    ['game' => $game, 'hp' => $hp] = basketballGame();

    expect(fn () => basket($game, GameEventType::FieldGoal, 'home', $hp[8]))->toThrow(InvalidArgumentException::class, 'em campo');
    expect(fn () => basket($game, GameEventType::Goal, 'home', $hp[0]))->toThrow(InvalidArgumentException::class, 'não usa gols');
    expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::Point, $game->home_entry_id, null))->toThrow(InvalidArgumentException::class, 'não usa pontos de rally');
});

it('does not allow baskets in football', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();

    expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::FieldGoal, $game->home_entry_id, null))->toThrow(InvalidArgumentException::class, 'não tem lances de 1, 2 ou 3 pontos');
});

describe('fouls', function () {
    it('eliminates a player on the fifth foul, who stays on court until replaced', function () {
        ['game' => $game, 'hp' => $hp] = basketballGame();

        foreach (range(1, 4) as $_) {
            basket($game, GameEventType::Foul, 'home', $hp[0]);
        }
        basket($game, GameEventType::FieldGoal, 'home', $hp[0]); // ainda pode pontuar com 4 faltas
        basket($game, GameEventType::Foul, 'home', $hp[0]); // 5ª falta

        expect(fn () => basket($game, GameEventType::FieldGoal, 'home', $hp[0]))->toThrow(InvalidArgumentException::class, 'eliminado por faltas')
            ->and(fn () => basket($game, GameEventType::Foul, 'home', $hp[0]))->toThrow(InvalidArgumentException::class, 'eliminado por faltas');

        // pode (e deve) ser substituído; o eliminado não volta
        app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Substitution, $game->home_entry_id, $hp[0], $hp[5], 1, 9);
        expect(fn () => app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Substitution, $game->home_entry_id, $hp[5], $hp[0], 1, 12))
            ->toThrow(InvalidArgumentException::class, 'eliminado por faltas');
    });

    it('counts team fouls per quarter, and carries overtime into the fourth', function () {
        ['game' => $game, 'hp' => $hp, 'ap' => $ap] = basketballGame();

        basket($game, GameEventType::Foul, 'home', $hp[0], 1);
        basket($game, GameEventType::Foul, 'home', $hp[1], 1);
        basket($game, GameEventType::Foul, 'away', $ap[0], 1);
        app(ChangeGamePeriod::class)->handle($game->fresh(), true, 1);
        expect(PublicGameSnapshot::for($game->fresh())['fouls'])->toBe(['home' => 2, 'away' => 1, 'limit' => 5, 'period' => 1]);

        app(ChangeGamePeriod::class)->handle($game->fresh(), false, 1);
        app(ChangeGamePeriod::class)->handle($game->fresh(), true, 2);
        expect(PublicGameSnapshot::for($game->fresh())['fouls']['home'])->toBe(0);

        foreach ([[2, 2], [3, 3]] as [$period]) {
            app(ChangeGamePeriod::class)->handle($game->fresh(), false, $period);
            app(ChangeGamePeriod::class)->handle($game->fresh(), true, $period + 1);
        }
        basket($game, GameEventType::Foul, 'home', $hp[2], 4);
        app(ChangeGamePeriod::class)->handle($game->fresh(), false, 4);
        app(ChangeGamePeriod::class)->handle($game->fresh(), true, 5);
        basket($game, GameEventType::Foul, 'home', $hp[3], 5);

        expect(PublicGameSnapshot::for($game->fresh())['fouls'])->toBe(['home' => 2, 'away' => 0, 'limit' => 5, 'period' => 4]);
    });

    it('rebuilds the foul count after voiding the last foul', function () {
        ['game' => $game, 'hp' => $hp] = basketballGame();
        foreach (range(1, 5) as $_) {
            basket($game, GameEventType::Foul, 'home', $hp[0]);
        }
        $last = $game->events()->where('type', GameEventType::Foul)->orderByDesc('id')->first();

        app(VoidGameEvent::class)->handle($game->fresh(), $last);

        basket($game, GameEventType::FieldGoal, 'home', $hp[0]);
        expect($game->fresh()->home_score)->toBe(2);
    });

    it('counts the unsportsmanlike foul in the team total but not the technical one', function () {
        ['game' => $game, 'hp' => $hp] = basketballGame();

        basket($game, GameEventType::Foul, 'home', $hp[0]);
        basket($game, GameEventType::UnsportsmanlikeFoul, 'home', $hp[1]);
        basket($game, GameEventType::TechnicalFoul, 'home', $hp[2]);

        expect(PublicGameSnapshot::for($game->fresh())['fouls']['home'])->toBe(2);
    });

    it('disqualifies on the second technical or unsportsmanlike foul, in any mix, and takes the player off court', function () {
        ['game' => $game, 'hp' => $hp] = basketballGame();

        basket($game, GameEventType::TechnicalFoul, 'home', $hp[0]);
        basket($game, GameEventType::FieldGoal, 'home', $hp[0]); // uma só ainda não desqualifica
        basket($game, GameEventType::UnsportsmanlikeFoul, 'home', $hp[0]);

        expect(fn () => basket($game, GameEventType::FieldGoal, 'home', $hp[0]))->toThrow(InvalidArgumentException::class, 'expulso')
            ->and(fn () => app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Substitution, $game->home_entry_id, $hp[0], $hp[5], 1, 3))
            ->toThrow(InvalidArgumentException::class);
    });

    it('also adds technical and unsportsmanlike fouls to the personal foul limit', function () {
        ['game' => $game, 'hp' => $hp] = basketballGame();

        foreach ([GameEventType::Foul, GameEventType::Foul, GameEventType::Foul, GameEventType::TechnicalFoul, GameEventType::Foul] as $type) {
            basket($game, $type, 'home', $hp[1]);
        }

        expect(fn () => basket($game, GameEventType::FieldGoal, 'home', $hp[1]))->toThrow(InvalidArgumentException::class, 'eliminado por faltas');
    });

    it('gives the unsportsmanlike foul back when voided', function () {
        ['game' => $game, 'hp' => $hp] = basketballGame();
        basket($game, GameEventType::UnsportsmanlikeFoul, 'home', $hp[0]);
        $event = $game->events()->where('type', GameEventType::UnsportsmanlikeFoul)->firstOrFail();

        app(VoidGameEvent::class)->handle($game->fresh(), $event);

        expect(PublicGameSnapshot::for($game->fresh())['fouls']['home'])->toBe(0);
    });

    it('is refused in football', function () {
        ['game' => $game, 'hp' => $hp] = sheetGame();

        expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::TechnicalFoul, $game->home_entry_id, $hp[0]))
            ->toThrow(InvalidArgumentException::class, 'não registra faltas');
    });
});

describe('countdown clock that stops', function () {
    it('shows the remaining time of the quarter and survives pause and resume', function () {
        ['game' => $game] = basketballGame();
        $this->actingAs(panelUser(Role::Operator));

        $this->post("/painel/games/{$game->id}/clock", ['pause' => true])->assertSessionHas('error', 'Inicie um período antes de parar o relógio.');
        $this->post("/painel/games/{$game->id}/periods", ['action' => 'start', 'period' => 1]);
        $this->post("/painel/games/{$game->id}/clock", ['pause' => true])->assertSessionHasNoErrors();
        $this->post("/painel/games/{$game->id}/clock", ['pause' => true])->assertSessionHas('error', 'O relógio já está parado.');

        $clock = PublicGameSnapshot::for($game->fresh())['clock'];
        expect($clock['state'])->toBe('paused')
            ->and($clock['direction'])->toBe('down')
            ->and($clock['period_seconds'])->toBe(600)
            ->and($clock['offset_seconds'])->toBe(0);

        $this->post("/painel/games/{$game->id}/clock", ['pause' => false])->assertSessionHasNoErrors();
        expect(PublicGameSnapshot::for($game->fresh())['clock']['state'])->toBe('running');

        $this->post("/painel/games/{$game->id}/periods", ['action' => 'end', 'period' => 1]);
        $this->post("/painel/games/{$game->id}/periods", ['action' => 'start', 'period' => 2]);
        expect(PublicGameSnapshot::for($game->fresh())['clock']['offset_seconds'])->toBe(600);
    });

    it('undoes the last clock mark and is refused in sports with a running clock', function () {
        ['game' => $game] = basketballGame();
        app(ChangeGamePeriod::class)->handle($game, true, 1);
        $pause = app(ChangeGamePeriod::class)->pause($game->fresh(), true);

        app(VoidGameEvent::class)->handle($game->fresh(), $pause);
        expect(PublicGameSnapshot::for($game->fresh())['clock']['state'])->toBe('running');

        ['stage' => $stage] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($stage);
        $football = Game::query()->where('stage_id', $stage->id)->firstOrFail();
        expect(fn () => app(ChangeGamePeriod::class)->pause($football, true))->toThrow(InvalidArgumentException::class, 'O relógio deste esporte não para.');
    });
});

describe('result, table and sheet', function () {
    it('does not finish a tied game and finishes a decided one', function () {
        ['game' => $game, 'hp' => $hp, 'ap' => $ap] = basketballGame();
        basket($game, GameEventType::FieldGoal, 'home', $hp[0]);
        basket($game, GameEventType::FieldGoal, 'away', $ap[0]);

        expect(fn () => app(FinishGame::class)->handle($game->fresh()))->toThrow(InvalidArgumentException::class, 'empatado');

        basket($game, GameEventType::ThreePointer, 'home', $hp[1], 5);
        $finished = app(FinishGame::class)->handle($game->fresh());

        expect($finished->status)->toBe(GameStatus::Finished)->and($finished->home_score)->toBe(5)->and($finished->away_score)->toBe(2);
    });

    it('accepts the quick result as a single anonymous total per team, refuses ties and can be corrected', function () {
        ['game' => $game] = basketballGame();

        expect(fn () => app(RecordQuickResult::class)->handle($game, 70, 70))->toThrow(InvalidArgumentException::class, 'não termina empatado');

        $done = app(RecordQuickResult::class)->handle($game, 78, 65);
        expect($done->home_score)->toBe(78)->and($done->away_score)->toBe(65)->and($game->events()->where('type', GameEventType::FieldGoal)->count())->toBe(2);

        app(ReopenGame::class)->handle($game->fresh());
        $again = app(RecordQuickResult::class)->handle($game->fresh(), 60, 81);
        expect($again->home_score)->toBe(60)->and($again->away_score)->toBe(81);
    });

    it('gives 2 points for the win, 1 for the loss and 0 to the team that loses by walkover', function () {
        ['stage' => $stage] = fixtureDivision(3);
        $stage->division->season->competition->update(['sport' => Sport::Basketball]);
        app(GenerateStageFixtures::class)->handle($stage);
        $games = Game::query()->where('stage_id', $stage->id)->orderBy('id')->get();
        [$first, $second] = [$games[0], $games[1]];

        app(RecordQuickResult::class)->handle($first, 80, 70);
        app(RegisterWalkover::class)->handle($second, $second->homeEntry);

        $rows = collect(app(CalculateStandings::class)->forStage($stage))->keyBy(fn ($row) => $row->entry->getKey());

        // vitória 2, derrota 1, derrota por W.O. 0
        $expected = [];
        foreach ([[$first->home_entry_id, 2], [$first->away_entry_id, 1], [$second->home_entry_id, 2], [$second->away_entry_id, 0]] as [$entry, $points]) {
            $expected[$entry] = ($expected[$entry] ?? 0) + $points;
        }

        foreach ($expected as $entry => $points) {
            expect($rows[$entry]->row->points)->toBe($points);
        }

        expect($second->fresh()->home_score)->toBe(20)->and($second->fresh()->away_score)->toBe(0);
    });

    it('serves the basketball sheet with its event types, per-player fouls and the pause button', function () {
        ['game' => $game, 'hp' => $hp] = basketballGame();
        basket($game, GameEventType::Foul, 'home', $hp[0]);
        $this->actingAs(panelUser(Role::Operator));

        $this->get("/painel/games/{$game->id}/sheet")->assertOk()->assertInertia(fn ($page) => $page
            ->where('sport', 'basketball')
            ->where('score_model', 'points')
            ->where('limits.starters', 5)
            ->where('limits.bench', 7)
            ->where('clock.max_period', 8)
            ->where('clock.pausable', true)
            ->where('home.player_fouls', [$hp[0] => 1])
            ->where('event_types', fn ($types) => collect($types)->pluck('value')->all() === ['field_goal', 'three_pointer', 'free_throw', 'foul', 'technical_foul', 'unsportsmanlike_foul', 'substitution']));
    });

    it('records a basket from the sheet pad request', function () {
        ['game' => $game, 'hp' => $hp] = basketballGame();
        $this->actingAs(panelUser(Role::Operator));

        $this->post("/painel/games/{$game->id}/events", ['type' => 'three_pointer', 'entry_id' => $game->home_entry_id, 'person_id' => $hp[0], 'period' => 6])->assertSessionHas('success');

        expect($game->fresh()->home_score)->toBe(3);
    });
});

it('starts a basketball season with the basketball regulation', function () {
    $this->actingAs(panelUser(Role::Organizer));
    $competition = Competition::factory()->create(['sport' => Sport::Basketball]);

    $this->get("/painel/competitions/{$competition->id}/seasons/create")->assertInertia(fn ($page) => $page
        ->where('rules.scoring.model', 'points')
        ->where('rules.points.win', 2)
        ->where('rules.points.loss', 1)
        ->where('rules.points.walkover_loss', 0)
        ->where('rules.fouls.max_player', 5)
        ->where('rules.lineup.max_bench', 7));

    $this->post("/painel/competitions/{$competition->id}/seasons", [
        'name' => 'Basquete 2030', 'year' => 2030, 'status' => 'draft',
        'rules' => [...$competition->sport->profile()->defaultRules()->toArray(), 'fouls' => ['max_player' => 6]],
    ])->assertSessionHasNoErrors();

    $rules = Season::query()->where('name', 'Basquete 2030')->firstOrFail()->ruleSet();
    expect($rules->maxPlayerFouls)->toBe(6)->and($rules->scoreModel)->toBe('points')->and($rules->pointsWalkoverLoss)->toBe(0);
});

function basketTimeout(Game $game, string $side, int $period = 1): void
{
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Timeout, $side === 'home' ? $game->home_entry_id : $game->away_entry_id, null, null, $period);
}

describe('team timeouts', function () {
    it('gives 2 timeouts per team in the first half, 3 in the second and 1 per overtime', function () {
        ['game' => $game] = basketballGame();

        basketTimeout($game, 'home', 1);
        basketTimeout($game, 'home', 2); // quartos 1 e 2 dividem os 2 pedidos da primeira metade

        expect(fn () => basketTimeout($game, 'home', 1))->toThrow(InvalidArgumentException::class, 'já usou os pedidos de tempo');

        basketTimeout($game, 'home', 3);
        basketTimeout($game, 'home', 3);
        basketTimeout($game, 'home', 4);

        expect(fn () => basketTimeout($game, 'home', 4))->toThrow(InvalidArgumentException::class)
            ->and($game->fresh()->events()->where('type', GameEventType::Timeout)->count())->toBe(5);

        basketTimeout($game, 'home', 5);
        expect(fn () => basketTimeout($game, 'home', 5))->toThrow(InvalidArgumentException::class);
        basketTimeout($game, 'home', 6); // cada prorrogação tem o seu
    });

    it('counts each team on its own and keeps the timeout out of the score and the athletes', function () {
        ['game' => $game] = basketballGame();

        basketTimeout($game, 'home');
        basketTimeout($game, 'home');
        basketTimeout($game, 'away');

        $game->refresh();
        $event = $game->events()->where('type', GameEventType::Timeout)->first();

        expect($game->home_score)->toBe(0)->and($game->away_score)->toBe(0)
            ->and($event->person_id)->toBeNull()
            ->and($game->status)->toBe(GameStatus::InProgress);
    });

    it('pauses the running clock automatically when a timeout is recorded', function () {
        ['game' => $game] = basketballGame();
        app(ChangeGamePeriod::class)->handle($game->fresh(), true, 1);

        basketTimeout($game, 'home', 1);

        expect($game->fresh()->events()->effective()->where('type', GameEventType::ClockPause)->count())->toBe(1)
            ->and(PublicGameSnapshot::for($game->fresh())['clock']['state'])->toBe('paused');
    });

    it('gives the timeout back when it is voided', function () {
        ['game' => $game] = basketballGame();

        basketTimeout($game, 'home');
        basketTimeout($game, 'home');
        $last = $game->events()->where('type', GameEventType::Timeout)->orderByDesc('id')->firstOrFail();
        app(VoidGameEvent::class)->handle($game->fresh(), $last);

        basketTimeout($game, 'home'); // volta a ter um pedido

        expect($game->fresh()->events()->effective()->where('type', GameEventType::Timeout)->count())->toBe(2);
    });

    it('is refused in sports that do not count timeouts', function () {
        ['game' => $game] = sheetGame();

        expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::Timeout, $game->home_entry_id, null))
            ->toThrow(InvalidArgumentException::class, 'não registra pedidos de tempo');
    });

    it('is recorded by the sheet screen without an athlete and shows how many were used', function () {
        ['game' => $game] = basketballGame();
        $user = panelUser(Role::Operator);

        $this->actingAs($user)->post(route('panel.games.events.store', $game), ['type' => 'timeout', 'entry_id' => $game->home_entry_id, 'period' => 1])
            ->assertSessionHas('success');

        $this->actingAs($user)->get(route('panel.games.sheet', $game))->assertInertia(fn ($page) => $page
            ->where('home.timeouts.used', 1)
            ->where('home.timeouts.limit', 2)
            ->where('away.timeouts.used', 0));
    });
});

describe('timeouts in the public snapshot', function () {
    it('shows how many timeouts each team still has in the current half, as numbers only', function () {
        ['game' => $game] = basketballGame();
        app(ChangeGamePeriod::class)->handle($game->fresh(), true, 1);

        expect(PublicGameSnapshot::for($game->fresh())['timeouts'])->toBe(['home' => 2, 'away' => 2, 'limit' => 2, 'period' => 1]);

        basketTimeout($game, 'home', 1);
        $snapshot = PublicGameSnapshot::for($game->fresh());

        expect($snapshot['timeouts'])->toBe(['home' => 1, 'away' => 2, 'limit' => 2, 'period' => 1])
            ->and(json_encode($snapshot['timeouts']))->not->toContain('person');
    });

    it('gives the timeout back when it is voided and has no entry in sports without timeouts', function () {
        ['game' => $game] = basketballGame();
        app(ChangeGamePeriod::class)->handle($game->fresh(), true, 1);
        basketTimeout($game, 'away', 1);
        $event = $game->fresh()->events()->where('type', GameEventType::Timeout)->firstOrFail();

        expect(PublicGameSnapshot::for($game->fresh())['timeouts']['away'])->toBe(1);

        app(VoidGameEvent::class)->handle($game->fresh(), $event);
        expect(PublicGameSnapshot::for($game->fresh())['timeouts']['away'])->toBe(2);

        ['game' => $football] = sheetGame();
        expect(PublicGameSnapshot::for($football)['timeouts'])->toBeNull();
    });
});

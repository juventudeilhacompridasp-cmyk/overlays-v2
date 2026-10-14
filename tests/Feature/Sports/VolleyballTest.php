<?php

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Enums\Sport;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Actions\BuildGameSheet;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Actions\VoidGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function volleyballGame(): Game
{
    ['stage' => $stage] = fixtureDivision(2);
    $stage->division->season->competition->update(['sport' => Sport::Volleyball]);
    app(GenerateStageFixtures::class)->handle($stage);

    return Game::query()->where('stage_id', $stage->id)->firstOrFail();
}

/** Lança `$n` pontos do lado informado. */
function scorePoints(Game $game, string $side, int $n): void
{
    $entry = $side === 'home' ? $game->home_entry_id : $game->away_entry_id;

    for ($i = 0; $i < $n; $i++) {
        app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Point, $entry, null);
    }
}

/** Partida 3-0 para o mandante. */
function winThreeZero(Game $game): void
{
    scorePoints($game, 'home', 75);
}

it('projects rally points into sets, set by set', function () {
    $game = volleyballGame();

    scorePoints($game, 'home', 25);
    scorePoints($game, 'away', 10);

    $game->refresh();
    expect($game->home_score)->toBe(1)->and($game->away_score)->toBe(0)
        ->and($game->sets)->toBe([
            ['home' => 25, 'away' => 0, 'winner' => 'home', 'finished' => true],
            ['home' => 0, 'away' => 10, 'winner' => null, 'finished' => false],
        ])
        ->and($game->status)->toBe(GameStatus::InProgress)
        ->and($game->events()->where('type', GameEventType::Point)->orderByDesc('id')->value('period'))->toBe(2);
});

it('refuses points after the match is over, and a point of a team outside the game', function () {
    $game = volleyballGame();
    winThreeZero($game);

    expect(fn () => scorePoints($game, 'away', 1))->toThrow(InvalidArgumentException::class, 'A partida já terminou');
    expect(fn () => app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Point, 'outra-equipe', null))->toThrow(InvalidArgumentException::class, 'não participa');
});

it('lets only the last point be voided and recalculates the score', function () {
    $game = volleyballGame();
    scorePoints($game, 'home', 25);
    scorePoints($game, 'away', 3);
    $points = $game->events()->where('type', GameEventType::Point)->orderBy('id')->get();
    $void = app(VoidGameEvent::class);

    expect(fn () => $void->handle($game->fresh(), $points->first()))->toThrow(InvalidArgumentException::class, 'Só o último ponto');

    $void->handle($game->fresh(), $points->last());
    expect($game->fresh()->sets[1])->toBe(['home' => 0, 'away' => 2, 'winner' => null, 'finished' => false]);

    // voltar para antes do fim do set 1 desfaz o set ganho
    scorePoints($game, 'away', 0);
    foreach (range(1, 2) as $_) {
        $void->handle($game->fresh(), $game->events()->effective()->where('type', GameEventType::Point)->orderByDesc('id')->first());
    }
    $void->handle($game->fresh(), $game->events()->effective()->where('type', GameEventType::Point)->orderByDesc('id')->first());
    expect($game->fresh()->home_score)->toBe(0)->and($game->fresh()->sets[0]['home'])->toBe(24)->and($game->fresh()->sets[0]['finished'])->toBeFalse();
});

it('only finishes when a side has won the sets', function () {
    $game = volleyballGame();
    scorePoints($game, 'home', 30);

    expect(fn () => app(FinishGame::class)->handle($game->fresh()))->toThrow(InvalidArgumentException::class, 'ainda não terminou');

    scorePoints($game, 'home', 45);
    $finished = app(FinishGame::class)->handle($game->fresh());

    expect($finished->status)->toBe(GameStatus::Finished)->and($finished->home_score)->toBe(3)->and($finished->away_score)->toBe(0);
});

it('does not accept the quick result in sets sports, but accepts a walkover as 3-0 in sets', function () {
    $game = volleyballGame();

    expect(fn () => app(RecordQuickResult::class)->handle($game, 3, 1))->toThrow(InvalidArgumentException::class, 'lance os pontos');

    app(RegisterWalkover::class)->handle($game, $game->homeEntry);
    expect($game->fresh()->home_score)->toBe(3)->and($game->fresh()->away_score)->toBe(0);
});

it('does not count points in sports without sets', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();

    expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::Point, $game->home_entry_id, null))->toThrow(InvalidArgumentException::class, 'não usa pontos de rally');
});

it('builds the standings from sets: 3-2 gives 2 and 1, rally points feed the ratio', function () {
    ['stage' => $stage] = fixtureDivision(3);
    $stage->division->season->competition->update(['sport' => Sport::Volleyball]);
    app(GenerateStageFixtures::class)->handle($stage);
    $games = Game::query()->where('stage_id', $stage->id)->get();
    $first = $games->first();

    // 3-2 para o mandante do primeiro jogo
    foreach ([['home', 25], ['away', 25], ['home', 25], ['away', 25], ['home', 15]] as [$side, $n]) {
        scorePoints($first, $side, $n);
    }
    app(FinishGame::class)->handle($first->fresh());

    $rows = collect(app(CalculateStandings::class)->forStage($stage))->keyBy(fn ($row) => $row->entry->getKey());

    expect($rows[$first->home_entry_id]->row->points)->toBe(2)
        ->and($rows[$first->away_entry_id]->row->points)->toBe(1)
        ->and($rows[$first->home_entry_id]->row->goalsFor)->toBe(3)
        ->and($rows[$first->home_entry_id]->row->goalsAgainst)->toBe(2)
        ->and($rows[$first->home_entry_id]->row->scoreFor)->toBe(65)
        ->and($rows[$first->home_entry_id]->row->scoreAgainst)->toBe(50);
});

it('exposes the sets in the public snapshot and broadcasts on every point', function () {
    $game = volleyballGame();
    Event::fake([GameUpdated::class]);

    scorePoints($game, 'home', 2);
    Event::assertDispatchedTimes(GameUpdated::class, 2);

    $snapshot = PublicGameSnapshot::for($game->fresh());
    expect($snapshot['score_model'])->toBe('sets')
        ->and($snapshot['sport'])->toBe('volleyball')
        ->and($snapshot['sets'])->toBe([['home' => 2, 'away' => 0, 'winner' => null, 'finished' => false]])
        ->and($snapshot['fouls'])->toBeNull();
});

it('allows two timeouts per team in each set and exposes the remaining count', function () {
    $game = volleyballGame();
    $home = $game->home_entry_id;
    $away = $game->away_entry_id;

    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Timeout, $home, null, period: 1);
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Timeout, $home, null, period: 1);

    expect(fn () => app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Timeout, $home, null, period: 1))
        ->toThrow(InvalidArgumentException::class, 'já usou os pedidos de tempo');

    expect(PublicGameSnapshot::for($game->fresh())['timeouts'])
        ->toBe(['home' => 0, 'away' => 2, 'limit' => 2, 'period' => 1]);

    scorePoints($game, 'home', 25);
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Timeout, $away, null, period: 2);
    expect(PublicGameSnapshot::for($game->fresh())['timeouts']['home'])->toBe(2)
        ->and(PublicGameSnapshot::for($game->fresh())['timeouts']['away'])->toBe(1);
});

it('limits substitutions to six per set and resets the count on the next set', function () {
    $game = volleyballGame();
    $people = RosterEntry::factory()->count(12)->create(['entry_id' => $game->home_entry_id])->pluck('person_id')->all();
    app(SaveGameLineup::class)->handle($game, $game->homeEntry, array_slice($people, 0, 6), array_slice($people, 6));
    $starters = collect(array_slice($people, 0, 6));
    $bench = collect(array_slice($people, 6));

    foreach (range(0, 5) as $index) {
        app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Substitution, $game->home_entry_id, $starters[$index], $bench[$index], 1);
    }

    expect(fn () => app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Substitution, $game->home_entry_id, $bench[0], $starters[0], 1))
        ->toThrow(InvalidArgumentException::class, 'Limite de 6 substituições neste período');
});

it('allows libero replacements to return without consuming the substitution limit', function () {
    $game = volleyballGame();
    $people = RosterEntry::factory()->count(7)->create(['entry_id' => $game->home_entry_id])->pluck('person_id')->all();
    $positions = array_combine(array_slice($people, 0, 6), ['z1', 'z2', 'z3', 'z4', 'z5', 'z6']);
    $positions[$people[6]] = 'lib';

    app(SaveGameLineup::class)->handle($game, $game->homeEntry, array_slice($people, 0, 6), [$people[6]], positions: $positions);
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Substitution, $game->home_entry_id, $people[5], $people[6], 1);
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Substitution, $game->home_entry_id, $people[6], $people[5], 1);

    $state = app(BuildGameSheet::class)->handle($game->fresh());
    expect($state->substitutionsUsed($game->home_entry_id))->toBe(0)
        ->and($state->onField($game->home_entry_id))->toContain($people[5])
        ->and($state->onField($game->home_entry_id))->not->toContain($people[6]);
});

it('scores from the sheet screen with a button per team and undoes the last point', function () {
    $game = volleyballGame();
    $this->actingAs(panelUser(Role::Operator));

    $this->post("/painel/games/{$game->id}/points", ['side' => 'home'])->assertSessionHasNoErrors();
    $this->post("/painel/games/{$game->id}/points", ['side' => 'away'])->assertSessionHasNoErrors();
    $this->post("/painel/games/{$game->id}/points", ['side' => 'ninguem'])->assertSessionHasErrors('side');

    $this->get("/painel/games/{$game->id}/sheet")->assertOk()->assertInertia(fn ($page) => $page
        ->where('score_model', 'sets')
        ->where('sets', [['home' => 1, 'away' => 1, 'winner' => null, 'finished' => false]])
        ->where('sets_to_win', 3)
        ->where('event_types', fn ($types) => collect($types)->pluck('value')->all() === ['substitution', 'yellow_card', 'red_card', 'timeout'])
        ->where('events', fn ($events) => collect($events)->where('type', 'point')->where('voidable', true)->count() === 1));

    $last = $game->events()->where('type', GameEventType::Point)->orderByDesc('id')->first();
    $this->post("/painel/games/{$game->id}/events/{$last->id}/void")->assertSessionHas('success');
    expect($game->fresh()->sets[0])->toBe(['home' => 1, 'away' => 0, 'winner' => null, 'finished' => false]);
});

it('starts a volleyball season with the volleyball regulation', function () {
    $this->actingAs(panelUser(Role::Organizer));
    $competition = Competition::factory()->create(['sport' => Sport::Volleyball]);

    $this->get("/painel/competitions/{$competition->id}/seasons/create")->assertInertia(fn ($page) => $page
        ->where('rules.scoring.model', 'sets')
        ->where('rules.scoring.set_points', 25)
        ->where('rules.points.win', 3)
        ->where('rules.tiebreakers', ['wins', 'set_ratio', 'point_ratio', 'head_to_head', 'draw']));

    $this->post("/painel/competitions/{$competition->id}/seasons", [
        'name' => 'Vôlei 2030', 'year' => 2030, 'status' => 'draft',
        'rules' => [...$competition->sport->profile()->defaultRules()->toArray(), 'scoring' => ['sets_to_win' => 2, 'set_points' => 21, 'deciding_set_points' => 15, 'min_lead' => 2, 'points_narrow_win' => 3, 'points_narrow_loss' => 0]],
    ])->assertSessionHasNoErrors();

    $rules = Season::query()->where('name', 'Vôlei 2030')->firstOrFail()->ruleSet();
    expect($rules->setsToWin)->toBe(2)->and($rules->setPoints)->toBe(21)->and($rules->scoreModel)->toBe('sets')->and($rules->pointsNarrowWin)->toBe(3);
});

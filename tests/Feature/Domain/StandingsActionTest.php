<?php

use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Models\Person;

/**
 * Liga de 4 equipes (E1..E4, nessa ordem de cabeça de chave) com jogos encerrados.
 *
 * @return array{stage: Stage, entries: list<Entry>}
 */
function leagueOfFour(?array $seasonRules = null): array
{
    $season = Season::factory()->create(['rules' => $seasonRules]);
    $division = Division::factory()->for($season)->create();
    $stage = Stage::factory()->for($division)->create(['type' => StageType::League]);

    $entries = [];
    foreach ([1, 2, 3, 4] as $seed) {
        $entries[] = Entry::factory()->for($division)->create(['seed' => $seed, 'status' => EntryStatus::Confirmed]);
    }

    return ['stage' => $stage, 'entries' => $entries];
}

function playGame(Stage $stage, Entry $home, Entry $away, int $homeGoals, int $awayGoals, array $cards = [], int $round = 1): Game
{
    $game = Game::factory()->for($stage)->create([
        'home_entry_id' => $home->id,
        'away_entry_id' => $away->id,
        'round' => $round,
        'scheduled_at' => now()->subDays(20 - $round)->setTime(15, 0),
    ]);

    foreach ([[$home, $homeGoals], [$away, $awayGoals]] as [$entry, $goals]) {
        for ($i = 0; $i < $goals; $i++) {
            GameEvent::factory()->create(['game_id' => $game->id, 'organization_id' => $game->organization_id, 'entry_id' => $entry->id, 'type' => GameEventType::Goal]);
        }
    }

    foreach ($cards as [$entry, $type, $person]) {
        GameEvent::factory()->create([
            'game_id' => $game->id, 'organization_id' => $game->organization_id,
            'entry_id' => $entry->id, 'type' => $type, 'person_id' => $person?->id,
        ]);
    }

    return app(FinishGame::class)->handle($game);
}

it('builds the league standings from finished games, walkovers and cards', function () {
    ['stage' => $stage, 'entries' => [$e1, $e2, $e3, $e4]] = leagueOfFour();

    playGame($stage, $e1, $e2, 2, 1, [[$e1, GameEventType::YellowCard, null], [$e2, GameEventType::RedCard, null]]);
    playGame($stage, $e3, $e4, 0, 0, round: 1);
    playGame($stage, $e1, $e3, 1, 1, round: 2);

    $walkover = Game::factory()->for($stage)->create(['home_entry_id' => $e2->id, 'away_entry_id' => $e4->id, 'round' => 2]);
    app(RegisterWalkover::class)->handle($walkover, $e2);

    // Jogo ainda não realizado: não conta.
    Game::factory()->for($stage)->create(['home_entry_id' => $e4->id, 'away_entry_id' => $e1->id, 'round' => 3]);

    $standings = app(CalculateStandings::class)->forStage($stage);
    $byEntry = collect($standings)->keyBy(fn ($s) => $s->entry->id);

    expect(collect($standings)->map(fn ($s) => $s->entry->id)->all())->toBe([$e1->id, $e2->id, $e3->id, $e4->id])
        ->and($byEntry[$e1->id]->row->points)->toBe(4)
        ->and($byEntry[$e1->id]->row->played)->toBe(2)
        ->and($byEntry[$e1->id]->row->yellowCards)->toBe(1)
        ->and($byEntry[$e2->id]->row->points)->toBe(3)
        ->and($byEntry[$e2->id]->row->goalsFor)->toBe(4)   // 1 do jogo + 3 do W.O.
        ->and($byEntry[$e2->id]->row->redCards)->toBe(1)
        ->and($byEntry[$e3->id]->row->points)->toBe(2)
        ->and($byEntry[$e4->id]->row->points)->toBe(1)
        ->and($byEntry[$e4->id]->row->played)->toBe(2)
        ->and($standings[0]->entry->team->name)->not->toBeEmpty();
});

it('uses the seed as the stable order for teams that remain tied', function () {
    ['stage' => $stage, 'entries' => [$e1, $e2, $e3, $e4]] = leagueOfFour();

    $standings = app(CalculateStandings::class)->forStage($stage);

    expect(collect($standings)->map(fn ($s) => $s->entry->id)->all())->toBe([$e1->id, $e2->id, $e3->id, $e4->id])
        ->and(collect($standings)->every(fn ($s) => $s->row->tied))->toBeTrue();
});

it('ignores cards voided by a compensating event in the standings', function () {
    ['stage' => $stage, 'entries' => [$e1, $e2]] = leagueOfFour();

    $game = playGame($stage, $e1, $e2, 1, 0, [[$e1, GameEventType::RedCard, null]]);
    $red = $game->events()->where('type', GameEventType::RedCard)->first();
    GameEvent::factory()->create([
        'game_id' => $game->id, 'organization_id' => $game->organization_id,
        'entry_id' => $e1->id, 'type' => GameEventType::RedCard, 'compensates_event_id' => $red->id,
    ]);

    $standings = collect(app(CalculateStandings::class)->forStage($stage))->keyBy(fn ($s) => $s->entry->id);

    expect($standings[$e1->id]->row->redCards)->toBe(0);
});

it('calculates standings for a group only with its own teams and games', function () {
    $season = Season::factory()->create();
    $division = Division::factory()->for($season)->create();
    $stage = Stage::factory()->for($division)->create(['type' => StageType::Groups]);
    $groupA = Group::factory()->for($stage)->create(['name' => 'Grupo A']);
    $groupB = Group::factory()->for($stage)->create(['name' => 'Grupo B']);

    $a1 = Entry::factory()->for($division)->create(['seed' => 1]);
    $a2 = Entry::factory()->for($division)->create(['seed' => 2]);
    $b1 = Entry::factory()->for($division)->create(['seed' => 3]);
    $b2 = Entry::factory()->for($division)->create(['seed' => 4]);
    $groupA->entries()->attach([$a1->id, $a2->id]);
    $groupB->entries()->attach([$b1->id, $b2->id]);

    $gameA = playGame($stage, $a1, $a2, 3, 0);
    $gameA->update(['group_id' => $groupA->id]);
    $gameB = playGame($stage, $b1, $b2, 0, 1);
    $gameB->update(['group_id' => $groupB->id]);

    $standingsA = app(CalculateStandings::class)->forGroup($groupA);
    $standingsB = app(CalculateStandings::class)->forGroup($groupB);

    expect(collect($standingsA)->map(fn ($s) => $s->entry->id)->all())->toBe([$a1->id, $a2->id])
        ->and($standingsA[0]->row->points)->toBe(3)
        ->and(collect($standingsB)->map(fn ($s) => $s->entry->id)->all())->toBe([$b2->id, $b1->id]);
});

it('only calculates a single standings table for league stages', function () {
    $stage = Stage::factory()->create(['type' => StageType::Knockout]);

    app(CalculateStandings::class)->forStage($stage);
})->throws(LogicException::class);

it('suspends a player after three yellow cards across games', function () {
    ['stage' => $stage, 'entries' => [$e1, $e2, $e3, $e4]] = leagueOfFour();
    $player = Person::factory()->create(['organization_id' => $e1->organization_id]);

    playGame($stage, $e1, $e2, 0, 0, [[$e1, GameEventType::YellowCard, $player]], round: 1);
    playGame($stage, $e3, $e1, 0, 0, [[$e1, GameEventType::YellowCard, $player]], round: 2);

    $tracker = app(CalculateDiscipline::class)->forEntry($e1);
    expect($tracker->isSuspended($player->id))->toBeFalse()
        ->and($tracker->seriesYellows($player->id))->toBe(2);

    playGame($stage, $e1, $e4, 0, 0, [[$e1, GameEventType::YellowCard, $player]], round: 3);

    $tracker = app(CalculateDiscipline::class)->forEntry($e1);
    expect($tracker->isSuspended($player->id))->toBeTrue()
        ->and($tracker->suspended())->toBe([$player->id => 1]);

    // O jogo seguinte da equipe cumpre a suspensão.
    playGame($stage, $e2, $e1, 0, 0, round: 4);

    expect(app(CalculateDiscipline::class)->forEntry($e1)->isSuspended($player->id))->toBeFalse();
});

it('serves a suspension on a walkover and ignores cards of the opponent team', function () {
    ['stage' => $stage, 'entries' => [$e1, $e2, $e3]] = leagueOfFour();
    $player = Person::factory()->create(['organization_id' => $e1->organization_id]);
    $rival = Person::factory()->create(['organization_id' => $e1->organization_id]);

    playGame($stage, $e1, $e2, 0, 0, [[$e1, GameEventType::RedCard, $player], [$e2, GameEventType::RedCard, $rival]], round: 1);

    $tracker = app(CalculateDiscipline::class)->forEntry($e1);
    expect($tracker->isSuspended($player->id))->toBeTrue()
        ->and($tracker->isSuspended($rival->id))->toBeFalse();

    $walkover = Game::factory()->for($stage)->create([
        'home_entry_id' => $e3->id, 'away_entry_id' => $e1->id, 'round' => 2,
        'scheduled_at' => now()->subDays(10),
    ]);
    app(RegisterWalkover::class)->handle($walkover, $e3);

    expect(app(CalculateDiscipline::class)->forEntry($e1)->isSuspended($player->id))->toBeFalse();
});

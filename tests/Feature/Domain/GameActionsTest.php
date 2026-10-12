<?php

use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\ProjectGameScore;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;

function addEvent(Game $game, Entry $entry, GameEventType $type, array $attributes = []): GameEvent
{
    return GameEvent::factory()->create(array_merge([
        'game_id' => $game->id,
        'organization_id' => $game->organization_id,
        'entry_id' => $entry->id,
        'type' => $type,
    ], $attributes));
}

it('projects the score from goals and counts an own goal for the opponent', function () {
    $game = Game::factory()->create();
    [$home, $away] = [$game->homeEntry, $game->awayEntry];

    addEvent($game, $home, GameEventType::Goal);
    addEvent($game, $home, GameEventType::Goal);
    addEvent($game, $away, GameEventType::Goal);
    addEvent($game, $away, GameEventType::OwnGoal); // gol contra do visitante vale para o mandante

    app(ProjectGameScore::class)->handle($game);

    expect($game->fresh()->home_score)->toBe(3)
        ->and($game->fresh()->away_score)->toBe(1)
        ->and($game->fresh()->home_penalties)->toBeNull();
});

it('ignores events voided by a compensating event', function () {
    $game = Game::factory()->create();
    $home = $game->homeEntry;

    addEvent($game, $home, GameEventType::Goal);
    $wrong = addEvent($game, $home, GameEventType::Goal);
    addEvent($game, $home, GameEventType::Goal, ['compensates_event_id' => $wrong->id]);

    app(ProjectGameScore::class)->handle($game);

    expect($game->fresh()->home_score)->toBe(1);
    expect($game->events()->effective()->count())->toBe(1);
});

it('projects the penalty shootout only when there are penalty kicks', function () {
    $game = Game::factory()->create();
    [$home, $away] = [$game->homeEntry, $game->awayEntry];

    foreach ([true, true, false] as $scored) {
        addEvent($game, $home, GameEventType::PenaltyKick, ['payload' => ['scored' => $scored]]);
    }
    foreach ([true, false, false] as $scored) {
        addEvent($game, $away, GameEventType::PenaltyKick, ['payload' => ['scored' => $scored]]);
    }

    app(ProjectGameScore::class)->handle($game);

    expect($game->fresh()->home_penalties)->toBe(2)
        ->and($game->fresh()->away_penalties)->toBe(1)
        ->and($game->fresh()->home_score)->toBe(0);
});

it('refuses to project or finish a game without both teams', function () {
    $game = Game::factory()->create(['away_entry_id' => null]);

    app(FinishGame::class)->handle($game);
})->throws(InvalidArgumentException::class);

it('finishes a game consolidating the score and refuses to finish twice', function () {
    $game = Game::factory()->create();
    addEvent($game, $game->awayEntry, GameEventType::Goal);

    app(FinishGame::class)->handle($game);
    $game->refresh();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($game->away_score)->toBe(1)
        ->and($game->finished_at)->not->toBeNull();

    app(FinishGame::class)->handle($game);
})->throws(InvalidArgumentException::class);

it('registers a walkover with the configured score for the winner side', function () {
    $season = Season::factory()->create(['rules' => ['walkover' => ['winner_goals' => 2, 'loser_goals' => 0]]]);
    $division = Division::factory()->for($season)->create();
    $stage = Stage::factory()->for($division)->create();
    $game = Game::factory()->for($stage)->create();

    app(RegisterWalkover::class)->handle($game, $game->awayEntry);
    $game->refresh();

    expect($game->status)->toBe(GameStatus::Walkover)
        ->and($game->home_score)->toBe(0)
        ->and($game->away_score)->toBe(2);
});

it('does not accept a walkover winner that is not in the game', function () {
    $game = Game::factory()->create();
    $stranger = Entry::factory()->create(['division_id' => $game->division_id]);

    app(RegisterWalkover::class)->handle($game, $stranger);
})->throws(InvalidArgumentException::class);

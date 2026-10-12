<?php

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Actions\ReopenGame;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Models\Person;

/** @return array{game: Game, home: Entry, away: Entry} */
function scheduledGame(): array
{
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);

    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();

    return ['game' => $game, 'home' => $game->homeEntry, 'away' => $game->awayEntry];
}

it('records a quick result as anonymous goal events and finishes the game', function () {
    ['game' => $game, 'home' => $home, 'away' => $away] = scheduledGame();

    app(RecordQuickResult::class)->handle($game, 3, 1);
    $game->refresh();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and([$game->home_score, $game->away_score])->toBe([3, 1])
        ->and($game->finished_at)->not->toBeNull()
        ->and($game->events()->count())->toBe(4)
        ->and($game->events()->where('entry_id', $home->id)->count())->toBe(3)
        ->and($game->events()->where('entry_id', $away->id)->count())->toBe(1)
        ->and($game->events->every(fn (GameEvent $e) => $e->payload === ['quick' => true] && $e->person_id === null))->toBeTrue();
});

it('records a goalless draw', function () {
    ['game' => $game] = scheduledGame();

    app(RecordQuickResult::class)->handle($game, 0, 0);

    expect($game->fresh()->status)->toBe(GameStatus::Finished)
        ->and($game->events()->count())->toBe(0);
});

it('corrects a quick result by reopening and recording again, voiding the previous goals', function () {
    ['game' => $game] = scheduledGame();

    app(RecordQuickResult::class)->handle($game, 2, 2);
    app(ReopenGame::class)->handle($game);

    expect($game->fresh()->status)->toBe(GameStatus::Scheduled);

    app(RecordQuickResult::class)->handle($game->fresh(), 1, 0);
    $game->refresh();

    expect([$game->home_score, $game->away_score])->toBe([1, 0])
        ->and($game->status)->toBe(GameStatus::Finished)
        ->and($game->events()->effective()->count())->toBe(1)
        ->and($game->events()->count())->toBe(4 + 4 + 1); // 4 originais + 4 compensatórios + 1 novo
});

it('refuses to record over a finished game or a detailed score sheet', function () {
    ['game' => $game, 'home' => $home] = scheduledGame();

    GameEvent::factory()->create([
        'game_id' => $game->id, 'organization_id' => $game->organization_id, 'entry_id' => $home->id,
        'type' => GameEventType::Goal, 'person_id' => Person::factory()->create(['organization_id' => $game->organization_id])->id,
    ]);

    expect(fn () => app(RecordQuickResult::class)->handle($game, 1, 0))
        ->toThrow(InvalidArgumentException::class, 'súmula');

    ['game' => $other] = scheduledGame();
    app(RecordQuickResult::class)->handle($other, 1, 0);

    expect(fn () => app(RecordQuickResult::class)->handle($other->fresh(), 2, 0))
        ->toThrow(InvalidArgumentException::class, 'encerrado');
});

it('rejects negative scores', function () {
    ['game' => $game] = scheduledGame();

    app(RecordQuickResult::class)->handle($game, -1, 0);
})->throws(InvalidArgumentException::class);

it('reopens a walkover resetting its score and refuses to reopen a scheduled game', function () {
    ['game' => $game, 'home' => $home] = scheduledGame();

    app(RegisterWalkover::class)->handle($game, $home);
    expect($game->fresh()->status)->toBe(GameStatus::Walkover)
        ->and($game->fresh()->home_score)->toBe(3);

    app(ReopenGame::class)->handle($game->fresh());

    expect($game->fresh()->status)->toBe(GameStatus::Scheduled)
        ->and([$game->fresh()->home_score, $game->fresh()->away_score])->toBe([0, 0]);

    app(ReopenGame::class)->handle($game->fresh());
})->throws(InvalidArgumentException::class, 'Só jogos encerrados');

it('feeds the standings from quick results, and a reopened game leaves the table', function () {
    ['stage' => $stage, 'entries' => [$e1, $e2, $e3]] = fixtureDivision(3);
    app(GenerateStageFixtures::class)->handle($stage);

    $find = fn (Entry $a, Entry $b) => Game::query()->where('stage_id', $stage->id)
        ->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('home_entry_id', $a->id)->where('away_entry_id', $b->id))
            ->orWhere(fn ($q) => $q->where('home_entry_id', $b->id)->where('away_entry_id', $a->id)))
        ->firstOrFail();

    $g12 = $find($e1, $e2);
    $g13 = $find($e1, $e3);

    $goals = fn (Game $game, Entry $winner) => $game->home_entry_id === $winner->id ? [2, 0] : [0, 2];
    app(RecordQuickResult::class)->handle($g12, ...$goals($g12, $e1));
    app(RecordQuickResult::class)->handle($g13, ...$goals($g13, $e1));

    $table = collect(app(CalculateStandings::class)->forStage($stage));
    expect($table->first()->entry->id)->toBe($e1->id)
        ->and($table->first()->row->points)->toBe(6)
        ->and($table->first()->row->played)->toBe(2);

    app(ReopenGame::class)->handle($g13);

    $table = collect(app(CalculateStandings::class)->forStage($stage))->keyBy(fn ($s) => $s->entry->id);
    expect($table[$e1->id]->row->points)->toBe(3)
        ->and($table[$e3->id]->row->played)->toBe(0);
});

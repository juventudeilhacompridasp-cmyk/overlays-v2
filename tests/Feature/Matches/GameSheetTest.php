<?php

use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Actions\VoidGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\RosterEntry;

it('records goals in the sheet, projects the score and starts the game', function () {
    ['game' => $game, 'home' => $home, 'away' => $away, 'hp' => $hp, 'ap' => $ap] = sheetGame();

    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 10);
    app(RecordGameEvent::class)->handle($game, GameEventType::OwnGoal, $away->id, $ap[1], minute: 30);

    $game->refresh();

    expect($game->status)->toBe(GameStatus::InProgress)
        ->and($game->started_at)->not->toBeNull()
        ->and([$game->home_score, $game->away_score])->toBe([2, 0]);
});

it('rejects invalid lineups', function () {
    ['game' => $game, 'home' => $home, 'away' => $away, 'hp' => $hp, 'ap' => $ap] = sheetGame();
    $fresh = Game::query()->find($game->id);

    // Pessoa de outra equipe.
    expect(fn () => app(SaveGameLineup::class)->handle($fresh, $home, [$ap[0]]))->toThrow(InvalidArgumentException::class, 'elenco ativo')
        // Mais titulares do que o permitido.
        ->and(fn () => app(SaveGameLineup::class)->handle($fresh, $home, array_slice($hp, 0, 12)))->toThrow(InvalidArgumentException::class, 'No máximo 11 titulares')
        // Duplicado.
        ->and(fn () => app(SaveGameLineup::class)->handle($fresh, $home, [$hp[0]], [$hp[0]]))->toThrow(InvalidArgumentException::class, 'duas vezes');
});

it('accepts staff members and refuses athletes in the staff slot', function () {
    ['game' => $game, 'home' => $home] = sheetGame();
    $coach = RosterEntry::factory()->create(['entry_id' => $home->id, 'role' => RosterRole::Coach, 'shirt_number' => null]);
    $athlete = RosterEntry::factory()->create(['entry_id' => $home->id]);

    app(SaveGameLineup::class)->handle($game, $home, [$athlete->person_id], [], [$coach->person_id]);

    expect($game->lineups()->where('entry_id', $home->id)->count())->toBe(2)
        ->and(fn () => app(SaveGameLineup::class)->handle($game, $home, [], [], [$athlete->person_id]))->toThrow(InvalidArgumentException::class, 'Atletas não entram como comissão')
        ->and(fn () => app(SaveGameLineup::class)->handle($game, $home, [$coach->person_id]))->toThrow(InvalidArgumentException::class, 'Só atletas');
});

it('blocks events from people outside the field and locks the lineup after the first event', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[12]))->toThrow(InvalidArgumentException::class, 'em campo');

    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);

    expect(fn () => app(SaveGameLineup::class)->handle($game->fresh(), $home, array_slice($hp, 0, 10)))->toThrow(InvalidArgumentException::class, 'não pode mais ser alterada');
});

it('records substitutions and lets the substitute score', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    app(RecordGameEvent::class)->handle($game, GameEventType::Substitution, $home->id, $hp[0], $hp[11], period: 2, minute: 60);
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[11], period: 2, minute: 70);

    expect($game->fresh()->home_score)->toBe(1)
        ->and(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]))->toThrow(InvalidArgumentException::class, 'em campo');
});

it('voids an event with a compensating event and recalculates the score', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    $goal = app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);
    expect($game->fresh()->home_score)->toBe(1);

    $voided = app(VoidGameEvent::class)->handle($game, $goal);

    expect($voided->compensates_event_id)->toBe($goal->id)
        ->and($game->fresh()->home_score)->toBe(0)
        ->and($game->events()->count())->toBe(2)
        ->and(fn () => app(VoidGameEvent::class)->handle($game, $goal))->toThrow(InvalidArgumentException::class, 'já foi anulado');
});

it('refuses to void a substitution when later events depend on it', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    $sub = app(RecordGameEvent::class)->handle($game, GameEventType::Substitution, $home->id, $hp[0], $hp[11], minute: 50);
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[11], minute: 60);

    expect(fn () => app(VoidGameEvent::class)->handle($game, $sub))->toThrow(InvalidArgumentException::class, 'dependem');
});

it('restores a player to the field when a red card is voided', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    $red = app(RecordGameEvent::class)->handle($game, GameEventType::RedCard, $home->id, $hp[0]);
    expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]))->toThrow(InvalidArgumentException::class);

    app(VoidGameEvent::class)->handle($game, $red);
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);

    expect($game->fresh()->home_score)->toBe(1);
});

it('closes the sheet to new events once the game is finished', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    app(FinishGame::class)->handle($game);

    expect(fn () => app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[0]))->toThrow(InvalidArgumentException::class, 'não está aberto');
});

it('suspends a player automatically after a red card in a finished game and refuses him in the next lineup', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    app(RecordGameEvent::class)->handle($game, GameEventType::RedCard, $home->id, $hp[0]);
    app(FinishGame::class)->handle($game);

    expect(app(CalculateDiscipline::class)->forEntry($home)->isSuspended($hp[0]))->toBeTrue();

    $next = Game::factory()->create([
        'stage_id' => $game->stage_id,
        'home_entry_id' => $home->id,
        'round' => 2,
    ]);

    expect(fn () => app(SaveGameLineup::class)->handle($next, $home, [$hp[0]]))->toThrow(InvalidArgumentException::class, 'suspenso');
});

it('does not allow detailed events to be mixed with a quick result', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);

    expect(fn () => app(RecordQuickResult::class)->handle($game->fresh(), 2, 0))->toThrow(InvalidArgumentException::class, 'súmula');
});

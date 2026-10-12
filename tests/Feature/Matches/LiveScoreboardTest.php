<?php

use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\VoidGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

it('starts the first half, puts the game in progress and broadcasts', function () {
    ['game' => $game] = sheetGame();
    Event::fake([GameUpdated::class]);

    app(ChangeGamePeriod::class)->handle($game, true, 1);

    $game->refresh();
    expect($game->status)->toBe(GameStatus::InProgress)
        ->and($game->started_at)->not->toBeNull();

    Event::assertDispatched(GameUpdated::class, fn (GameUpdated $event) => $event->gameId === $game->id
        && $event->snapshot['clock']['state'] === 'running'
        && $event->snapshot['clock']['period'] === 1
        && $event->broadcastOn()[0]->name === "games.{$game->id}"
        && $event->broadcastAs() === 'game.updated');
});

it('enforces the order of periods and freezes the clock at the end of one', function () {
    ['game' => $game] = sheetGame();
    $change = app(ChangeGamePeriod::class);

    expect(fn () => $change->handle($game, false, 1))->toThrow(InvalidArgumentException::class, 'ainda não foi iniciado');

    $change->handle($game, true, 1);

    expect(fn () => $change->handle($game, true, 2))->toThrow(InvalidArgumentException::class, 'Encerre o período anterior');

    $change->handle($game, false, 1);
    $clock = PublicGameSnapshot::for($game->fresh())['clock'];

    expect($clock['state'])->toBe('stopped')->and($clock['running_since_ms'])->toBeNull();

    $change->handle($game, true, 2);
    $clock = PublicGameSnapshot::for($game->fresh())['clock'];

    expect($clock['state'])->toBe('running')->and($clock['period'])->toBe(2)->and($clock['base_seconds'])->toBe(2700);
});

it('broadcasts a score change with the goal scorer and credits own goals to the opponent', function () {
    ['game' => $game, 'home' => $home, 'away' => $away, 'hp' => $hp, 'ap' => $ap] = sheetGame();
    Person::query()->whereKey($hp[0])->update(['nickname' => 'Rafa']);
    Event::fake([GameUpdated::class]);

    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 12);
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::OwnGoal, $away->id, $ap[1], minute: 30);

    Event::assertDispatched(GameUpdated::class, fn (GameUpdated $event) => $event->snapshot['home_score'] === 2);

    $snapshot = PublicGameSnapshot::for($game->fresh());

    expect([$snapshot['home_score'], $snapshot['away_score']])->toBe([2, 0])
        ->and($snapshot['goals'])->toHaveCount(2)
        ->and($snapshot['goals'][0])->toMatchArray(['side' => 'home', 'player' => 'Rafa', 'minute' => 12, 'own_goal' => false])
        ->and($snapshot['goals'][1])->toMatchArray(['side' => 'home', 'own_goal' => true]);
});

it('keeps cards, lineups and personal data out of the public snapshot', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    app(RecordGameEvent::class)->handle($game, GameEventType::RedCard, $home->id, $hp[0]);
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::YellowCard, $home->id, $hp[1]);

    $snapshot = PublicGameSnapshot::for($game->fresh());
    $json = json_encode($snapshot);

    expect($snapshot['goals'])->toBe([])
        ->and($snapshot)->not->toHaveKeys(['cards', 'lineup', 'events', 'home_lineup'])
        ->and($json)->not->toContain($hp[0])
        ->and($json)->not->toContain('document')
        ->and($json)->not->toContain('birth');
});

it('broadcasts when the game is finished', function () {
    ['game' => $game] = sheetGame();
    Event::fake([GameUpdated::class]);

    app(FinishGame::class)->handle($game);

    Event::assertDispatched(GameUpdated::class, fn (GameUpdated $event) => $event->snapshot['finished'] === true && $event->snapshot['status'] === 'finished');
});

it('only lets the last period mark be voided and broadcasts the reverted clock', function () {
    ['game' => $game] = sheetGame();
    $change = app(ChangeGamePeriod::class);
    $first = $change->handle($game, true, 1);
    $second = $change->handle($game, false, 1);

    expect(fn () => app(VoidGameEvent::class)->handle($game, $first))->toThrow(InvalidArgumentException::class, 'último marco');

    Event::fake([GameUpdated::class]);
    app(VoidGameEvent::class)->handle($game, $second);

    Event::assertDispatched(GameUpdated::class, fn (GameUpdated $event) => $event->snapshot['clock']['state'] === 'running');
    expect(GameEvent::query()->where('game_id', $game->id)->count())->toBe(3);
});

it('shows the public live page to guests without exposing the organization panel', function () {
    ['game' => $game] = sheetGame();

    $this->get("/jogos/{$game->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/GameLive')
            ->where('snapshot.id', $game->id)
            ->where('snapshot.status', 'scheduled')
            ->has('snapshot.home.name')
            ->has('realtime.port')
            ->missing('snapshot.events'));

    $this->getJson("/jogos/{$game->id}/estado")->assertOk()->assertJsonPath('id', $game->id)->assertJsonPath('clock.state', 'not_started');
    $this->get('/jogos/inexistente')->assertNotFound();
});

it('controls the clock from the panel', function () {
    ['game' => $game] = sheetGame();
    $this->actingAs(panelUser());

    $this->post("/painel/games/{$game->id}/periods", ['action' => 'start', 'period' => 1])->assertSessionHas('success');
    $this->post("/painel/games/{$game->id}/periods", ['action' => 'start', 'period' => 1])->assertSessionHas('error', 'Este período já foi iniciado.');
    $this->post("/painel/games/{$game->id}/periods", ['action' => 'nope', 'period' => 1])->assertSessionHasErrors('action');

    $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page
        ->where('clock.state', 'running')
        ->where('clock.max_period', 4)
        ->where('public_url', route('public.games.show', $game->id))
        ->has('events', 1)
        ->where('events.0.type', 'period_start')
        ->where('events.0.voidable', true));
});

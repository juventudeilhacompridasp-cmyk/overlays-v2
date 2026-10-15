<?php

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\SheetCommand;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** Item da fila do aparelho: chave própria, tipo, hora do lance (ms) e dados. */
function queueItem(string $kind, array $payload, Carbon $at, ?string $key = null): array
{
    return ['key' => $key ?? (string) Str::uuid(), 'kind' => $kind, 'at' => $at->getTimestamp() * 1000, 'payload' => $payload];
}

describe('applying the queue', function () {
    it('applies an offline quick result with the device timestamp', function () {
        ['game' => $game] = sheetGame();
        $at = now()->subMinutes(2)->startOfSecond();

        $response = $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $game), ['items' => [
            queueItem('quick_result', ['home_score' => 3, 'away_score' => 1], $at),
        ]])->assertOk();

        expect($response->json('results.0.status'))->toBe('applied')
            ->and($game->fresh()->home_score)->toBe(3)
            ->and($game->fresh()->away_score)->toBe(1)
            ->and($game->fresh()->status)->toBe(GameStatus::Finished)
            ->and($game->events()->first()->created_at->getTimestamp())->toBe($at->getTimestamp());
    });

    it('applies and undoes offline shootout kicks in order', function () {
        ['stage' => $stage] = fixtureDivision(2, StageType::Knockout);
        app(GenerateStageFixtures::class)->handle($stage);
        $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
        $at = now()->subMinutes(2)->startOfSecond();

        $response = $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $game), ['items' => [
            queueItem('shootout_kick', ['side' => 'home', 'scored' => true], $at),
            queueItem('shootout_kick', ['side' => 'away', 'scored' => false], $at->copy()->addSecond()),
        ]])->assertOk();

        expect(collect($response->json('results'))->pluck('status')->all())->toBe(['applied', 'applied'])
            ->and($game->events()->effective()->where('type', GameEventType::PenaltyKick)->count())->toBe(2);
    });

    it('applies the batch in order with the time each play really happened', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        $start = now()->subMinutes(50)->startOfSecond();

        $response = $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $game), ['items' => [
            queueItem('period', ['start' => true, 'period' => 1], $start),
            queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1, 'minute' => 12], $start->copy()->addMinutes(12)),
            queueItem('event', ['type' => 'substitution', 'entry_id' => $home->id, 'person_id' => $hp[1], 'secondary_person_id' => $hp[11], 'period' => 1, 'minute' => 30], $start->copy()->addMinutes(30)),
            queueItem('period', ['start' => false, 'period' => 1], $start->copy()->addMinutes(45)),
        ]])->assertOk();

        expect(collect($response->json('results'))->pluck('status')->all())->toBe(['applied', 'applied', 'applied', 'applied']);

        $events = $game->events()->orderBy('id')->get();
        $game->refresh();

        expect($events->pluck('type')->map->value->all())->toBe(['period_start', 'goal', 'substitution', 'period_end'])
            ->and($events[0]->created_at->getTimestamp())->toBe($start->getTimestamp())
            ->and($events[3]->created_at->getTimestamp())->toBe($start->copy()->addMinutes(45)->getTimestamp())
            ->and($events->pluck('recorded_by')->unique()->count())->toBe(1)
            ->and($game->started_at->getTimestamp())->toBe($start->getTimestamp())
            ->and($game->home_score)->toBe(1)
            ->and(SheetCommand::query()->where('game_id', $game->id)->count())->toBe(4);
    });

    it('does not apply the same keys twice when the batch is resent', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        $operator = panelUser(Role::Operator);
        $items = [queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1], now()->subMinute())];

        $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => $items])->assertOk();
        $again = $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => $items])->assertOk();

        expect($again->json('results.0.status'))->toBe('duplicate')
            ->and($game->events()->count())->toBe(1)
            ->and($game->fresh()->home_score)->toBe(1);
    });

    it('stops at the first rejected play, reports why and skips the rest', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

        $response = $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $game), ['items' => [
            queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1], now()->subMinutes(3)),
            queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[12], 'period' => 1], now()->subMinutes(2)), // reserva: não está em campo
            queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[2], 'period' => 1], now()->subMinute()),
        ]])->assertOk();

        expect($response->json('results.0.status'))->toBe('applied')
            ->and($response->json('results.1.status'))->toBe('rejected')
            ->and($response->json('results.1.message'))->toContain('em campo')
            ->and($response->json('results.2.status'))->toBe('skipped')
            ->and($game->events()->count())->toBe(1)
            ->and(SheetCommand::query()->count())->toBe(1);
    });

    it('lets the operator fix and resend after a rejection: the applied keys stay applied', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        $operator = panelUser(Role::Operator);
        $first = queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1], now()->subMinutes(3));
        $bad = queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[12], 'period' => 1], now()->subMinutes(2));
        $last = queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[2], 'period' => 1], now()->subMinute());

        $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => [$first, $bad, $last]]);

        // O mesário descarta o item recusado e reenvia o que sobrou (o primeiro já estava aplicado).
        $retry = $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => [$first, $last]])->assertOk();

        expect(collect($retry->json('results'))->pluck('status')->all())->toBe(['duplicate', 'applied'])
            ->and($game->fresh()->home_score)->toBe(2);
    });

    it('never records a play in the future', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

        $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $game), ['items' => [
            queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1], now()->addHours(2)),
        ]])->assertOk();

        expect($game->events()->firstOrFail()->created_at->lte(now()->addSecond()))->toBeTrue();
    });

    it('voids a synced play and refuses to void one that does not exist', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        $goal = app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);
        $operator = panelUser(Role::Operator);

        $ok = $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => [queueItem('void', ['event_id' => $goal->id], now())]])->assertOk();
        expect($ok->json('results.0.status'))->toBe('applied')
            ->and($game->fresh()->home_score)->toBe(0);

        $missing = $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => [queueItem('void', ['event_id' => (string) Str::ulid()], now())]])->assertOk();
        expect($missing->json('results.0.status'))->toBe('rejected')
            ->and($missing->json('results.0.message'))->toContain('não existe');
    });

    it('finishes the game with the time of the final whistle and closes the sheet', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        $whistle = now()->subMinutes(5)->startOfSecond();

        $response = $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $game), ['items' => [
            queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1], $whistle->copy()->subMinutes(10)),
            queueItem('finish', [], $whistle),
            queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[1], 'period' => 1], $whistle->copy()->addSecond()),
        ]])->assertOk();

        expect($response->json('results.1.status'))->toBe('applied')
            ->and($response->json('results.2.status'))->toBe('rejected')
            ->and($game->fresh()->status)->toBe(GameStatus::Finished)
            ->and($game->fresh()->finished_at->getTimestamp())->toBe($whistle->getTimestamp());
    });

    it('applies rally points of volleyball and clock pauses of basketball', function () {
        ['stage' => $stage] = fixtureDivision(2);
        $stage->division->season->competition->update(['sport' => Sport::Volleyball]);
        app(GenerateStageFixtures::class)->handle($stage);
        $volley = Game::query()->where('stage_id', $stage->id)->firstOrFail();
        $operator = panelUser(Role::Operator);

        $points = collect(range(1, 25))->map(fn () => queueItem('point', ['side' => 'home'], now()->subMinute()))->all();
        $this->actingAs($operator)->postJson(route('panel.games.sync', $volley), ['items' => $points])->assertOk();
        expect($volley->fresh()->home_score)->toBe(1); // um set ganho

        ['stage' => $stage2] = fixtureDivision(2);
        $stage2->division->season->competition->update(['sport' => Sport::Basketball]);
        app(GenerateStageFixtures::class)->handle($stage2);
        $basket = Game::query()->where('stage_id', $stage2->id)->firstOrFail();
        $start = now()->subMinutes(5)->startOfSecond();

        $response = $this->actingAs($operator)->postJson(route('panel.games.sync', $basket), ['items' => [
            queueItem('period', ['start' => true, 'period' => 1], $start),
            queueItem('clock', ['pause' => true], $start->copy()->addMinute()),
            queueItem('clock', ['pause' => false], $start->copy()->addMinutes(2)),
        ]])->assertOk();

        expect(collect($response->json('results'))->pluck('status')->all())->toBe(['applied', 'applied', 'applied'])
            ->and($basket->events()->whereIn('type', ['clock_pause', 'clock_resume'])->count())->toBe(2);
    });

    it('syncs basketball shot-clock resets from the offline queue', function () {
        ['stage' => $stage] = fixtureDivision(2);
        $stage->division->season->competition->update(['sport' => Sport::Basketball]);
        app(GenerateStageFixtures::class)->handle($stage);
        $basket = Game::query()->where('stage_id', $stage->id)->firstOrFail();
        $start = now()->subSeconds(20)->startOfSecond();

        $response = $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $basket), ['items' => [
            queueItem('period', ['start' => true, 'period' => 1], $start),
            queueItem('shot_clock', ['seconds' => 14], $start->copy()->addSeconds(8)),
            queueItem('clock', ['pause' => true], $start->copy()->addSeconds(13)),
        ]])->assertOk();

        $shotClock = PublicGameSnapshot::for($basket->fresh())['shot_clock'];

        expect(collect($response->json('results'))->pluck('status')->all())->toBe(['applied', 'applied', 'applied'])
            ->and($basket->events()->where('type', GameEventType::ShotClockReset)->count())->toBe(1)
            ->and(SheetCommand::query()->where('kind', 'shot_clock')->count())->toBe(1)
            ->and($shotClock['state'])->toBe('paused')
            ->and($shotClock['base_seconds'])->toBe(9);
    });
});

describe('access and validation', function () {
    it('is for those who operate games', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        $body = ['items' => [queueItem('event', ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1], now())]];

        $this->postJson(route('panel.games.sync', $game), $body)->assertUnauthorized();
        $this->actingAs(panelUser(Role::Viewer))->postJson(route('panel.games.sync', $game), $body)->assertForbidden();
        $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $game), $body)->assertOk();

        expect(GameEvent::query()->count())->toBe(1);
    });

    it('rejects malformed requests', function () {
        ['game' => $game] = sheetGame();
        $operator = panelUser(Role::Operator);
        $valid = queueItem('finish', [], now());

        $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => []])->assertUnprocessable();
        $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => [[...$valid, 'kind' => 'apagar-tudo']]])->assertUnprocessable();
        $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => [[...$valid, 'at' => -1]]])->assertUnprocessable();
        $this->actingAs($operator)->postJson(route('panel.games.sync', $game), ['items' => array_fill(0, 201, $valid)])->assertUnprocessable();
    });

    it('does not let a play touch another game', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        ['stage' => $other] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($other);
        $otherGame = Game::query()->where('stage_id', $other->id)->firstOrFail();
        $foreign = app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);

        $response = $this->actingAs(panelUser(Role::Operator))->postJson(route('panel.games.sync', $otherGame), ['items' => [queueItem('void', ['event_id' => $foreign->id], now())]])->assertOk();

        expect($response->json('results.0.status'))->toBe('rejected')
            ->and($game->fresh()->home_score)->toBe(1);
    });
});

describe('what the sheet page gives the browser to project the queue', function () {
    it('sends the clock offsets, the substitution rules, yellow counts and who made each play', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        app(RecordGameEvent::class)->handle($game, GameEventType::YellowCard, $home->id, $hp[0]);

        $this->actingAs(panelUser(Role::Operator))->get(route('panel.games.sheet', $game))->assertInertia(fn ($page) => $page
            ->where('clock_offsets', [0, 2700, 5400, 6300])
            ->where('limits.reentry', false)
            ->where('limits.max_player_fouls', null)
            ->where("home.yellows.{$hp[0]}", 1)
            ->where('events.0.person_id', $hp[0]));
    });
});

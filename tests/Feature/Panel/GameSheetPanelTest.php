<?php

use App\Models\User;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));

    $this->actingAs(User::factory()->create());
});

it('shows the sheet with rosters, lineups and no events', function () {
    ['game' => $game, 'home' => $home] = sheetGame();

    $this->get("/painel/games/{$game->id}/sheet")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Panel/Games/Sheet')
            ->where('game.open', true)
            ->where('home.id', $home->id)
            ->where('home.has_lineup', true)
            ->where('home.has_events', false)
            ->has('home.roster', 14)
            ->has('home.on_field', 11)
            ->has('home.available_bench', 3)
            ->where('limits.starters', 11)
            ->has('events', 0));
});

it('records, lists and voids events through the panel', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    $this->post("/painel/games/{$game->id}/events", [
        'type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1, 'minute' => 12,
    ])->assertRedirect()->assertSessionHas('success');

    $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page
        ->where('game.home_score', 1)
        ->where('game.status', 'in_progress')
        ->has('events', 1)
        ->where('events.0.minute', 12)
        ->where('events.0.voidable', true)
        ->where('home.has_events', true));

    $event = GameEvent::query()->where('game_id', $game->id)->firstOrFail();

    $this->post("/painel/games/{$game->id}/events/{$event->id}/void")->assertSessionHas('success', 'Lançamento anulado.');

    $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page
        ->where('game.home_score', 0)
        ->has('events', 0));
});

it('reports rule violations as an error message', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    $this->post("/painel/games/{$game->id}/events", [
        'type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[12], 'period' => 1,
    ])->assertSessionHas('error', 'Só quem está em campo pode marcar gol.');
});

it('validates the event payload', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    $this->post("/painel/games/{$game->id}/events", ['type' => 'goal', 'entry_id' => $home->id])
        ->assertSessionHasErrors(['person_id', 'period']);

    $this->post("/painel/games/{$game->id}/events", [
        'type' => GameEventType::Substitution->value, 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1,
    ])->assertSessionHasErrors(['secondary_person_id']);
});

it('saves a lineup through the panel and shows suspended players', function () {
    ['game' => $game, 'away' => $away, 'ap' => $ap] = sheetGame();

    $this->put("/painel/games/{$game->id}/lineups/{$away->id}", [
        'starters' => array_slice($ap, 0, 11), 'bench' => array_slice($ap, 11), 'staff' => [],
    ])->assertSessionHas('success', 'Escalação salva.');

    $this->put("/painel/games/{$game->id}/lineups/{$away->id}", ['starters' => array_slice($ap, 0, 12), 'bench' => [], 'staff' => []])
        ->assertSessionHas('error', 'No máximo 11 titulares.');
});

it('finishes the game from the sheet', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();

    $this->post("/painel/games/{$game->id}/events", ['type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1]);
    $this->post("/painel/games/{$game->id}/finish")->assertSessionHas('success', 'Jogo encerrado.');

    expect($game->fresh()->status)->toBe(GameStatus::Finished);

    $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page->where('game.open', false)->where('events.0.voidable', false));
});

it('does not open sheets of other organizations', function () {
    ['game' => $game] = sheetGame();

    CurrentOrganization::set(Organization::factory()->create(['slug' => 'outra']));
    config(['platform.default_organization_slug' => 'outra']);

    $this->get("/painel/games/{$game->id}/sheet")->assertNotFound();
});

it('opens the sheet of a game decided with a quick result', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    app(RecordQuickResult::class)->handle($game, 2, 1);

    $this->get("/painel/games/{$game->id}/sheet")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('game.open', false)
            ->where('game.home_score', 2)
            ->has('events', 3)
            ->where('events.0.quick', true)
            ->where('events.0.voidable', false));
});

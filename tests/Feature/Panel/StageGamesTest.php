<?php

use App\Models\User;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\Venue;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));

    $this->actingAs(User::factory()->create());
});

it('shows the stage with its standings table, games and generation flags', function () {
    ['stage' => $stage] = fixtureDivision(4);

    $this->get("/painel/stages/{$stage->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Panel/Stages/Show')
            ->where('stage.type', 'league')
            ->has('games', 0)
            ->has('tables', 1)
            ->has('tables.0.rows', 4)
            ->where('generation.can_generate', true)
            ->where('generation.confirmed_entries', 4)
            ->where('generation.can_clear', false));
});

it('generates the fixtures through the panel and then offers to clear them', function () {
    ['stage' => $stage] = fixtureDivision(4);

    $this->post("/painel/stages/{$stage->id}/fixtures", ['double_round' => false])
        ->assertRedirect()
        ->assertSessionHas('success', 'Tabela gerada com 6 jogos.');

    $this->get("/painel/stages/{$stage->id}")
        ->assertInertia(fn ($page) => $page
            ->has('games', 6)
            ->where('games.0.status', 'scheduled')
            ->where('games.0.status_label', 'Agendado')
            ->where('generation.can_generate', false)
            ->where('generation.can_clear', true));

    $this->post("/painel/stages/{$stage->id}/fixtures", ['double_round' => false])
        ->assertSessionHas('error');

    $this->delete("/painel/stages/{$stage->id}/fixtures")->assertSessionHas('success');
    expect(Game::query()->count())->toBe(0);
});

it('requires the number of groups for a group stage and groups its tables', function () {
    ['stage' => $stage] = fixtureDivision(6, StageType::Groups);

    $this->post("/painel/stages/{$stage->id}/fixtures", ['double_round' => false])->assertSessionHasErrors('groups');
    $this->post("/painel/stages/{$stage->id}/fixtures", ['double_round' => true, 'groups' => 2])->assertSessionHas('success');

    $this->get("/painel/stages/{$stage->id}")
        ->assertInertia(fn ($page) => $page
            ->has('tables', 2)
            ->where('tables.0.name', 'Grupo A')
            ->has('tables.0.rows', 3)
            ->has('games', 12)
            ->where('games.0.group', 'Grupo A'));
});

it('reports a friendly error when there are not enough teams', function () {
    ['stage' => $stage] = fixtureDivision(1);

    $this->post("/painel/stages/{$stage->id}/fixtures", ['double_round' => false])
        ->assertSessionHas('error', 'Confirme pelo menos duas equipes na divisão para gerar a tabela.');
});

it('schedules a game with date, venue and status', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->firstOrFail();
    $venue = Venue::factory()->create();

    $this->patch("/painel/games/{$game->id}", ['scheduled_at' => '2026-05-10T16:30', 'venue_id' => $venue->id, 'status' => 'scheduled'])
        ->assertSessionHas('success');

    expect($game->fresh()->venue_id)->toBe($venue->id)
        ->and($game->fresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-05-10 16:30');

    $this->patch("/painel/games/{$game->id}", ['status' => 'postponed'])->assertSessionHas('success');
    expect($game->fresh()->status)->toBe(GameStatus::Postponed);
});

it('validates the schedule fields and rejects venues from another organization', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->firstOrFail();
    $foreign = Venue::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->patch("/painel/games/{$game->id}", ['status' => 'finished'])->assertSessionHasErrors('status');
    $this->patch("/painel/games/{$game->id}", ['status' => 'scheduled', 'venue_id' => $foreign->id])->assertSessionHasErrors('venue_id');
    $this->patch("/painel/games/{$game->id}", ['status' => 'scheduled', 'scheduled_at' => 'amanhã'])->assertSessionHasErrors('scheduled_at');
});

it('records a result, shows it in the standings, then reopens it', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->firstOrFail();

    $this->post("/painel/games/{$game->id}/result", ['home_score' => 2, 'away_score' => 1])->assertSessionHas('success', 'Resultado lançado.');

    $this->get("/painel/stages/{$stage->id}")
        ->assertInertia(fn ($page) => $page
            ->where('games.0.decided', true)
            ->where('games.0.home_score', 2)
            ->where('games.0.status_label', 'Encerrado')
            ->where('tables.0.rows.0.points', 3)
            ->where('tables.0.rows.0.played', 1));

    $this->patch("/painel/games/{$game->id}", ['status' => 'postponed'])->assertSessionHas('error');
    $this->post("/painel/games/{$game->id}/result", ['home_score' => 0, 'away_score' => 0])->assertSessionHas('error');

    $this->post("/painel/games/{$game->id}/reopen")->assertSessionHas('success');
    expect($game->fresh()->status)->toBe(GameStatus::Scheduled);
});

it('validates result scores', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->firstOrFail();

    $this->post("/painel/games/{$game->id}/result", ['home_score' => -1, 'away_score' => 'x'])
        ->assertSessionHasErrors(['home_score', 'away_score']);
    $this->post("/painel/games/{$game->id}/result", ['home_score' => 100, 'away_score' => 0])->assertSessionHasErrors('home_score');
});

it('registers a walkover for the chosen side', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->firstOrFail();

    $this->post("/painel/games/{$game->id}/walkover", ['winner' => 'away'])->assertSessionHas('success');
    $this->post("/painel/games/{$game->id}/walkover", ['winner' => 'nobody'])->assertSessionHasErrors('winner');

    $game->refresh();
    expect($game->status)->toBe(GameStatus::Walkover)
        ->and([$game->home_score, $game->away_score])->toBe([0, 3]);
});

it('does not expose stages or games of another organization', function () {
    $otherOrg = Organization::factory()->create();
    $stage = CurrentOrganization::using($otherOrg, function () {
        ['stage' => $stage] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($stage);

        return $stage;
    });
    $game = Game::withoutGlobalScopes()->where('stage_id', $stage->id)->firstOrFail();

    $this->get("/painel/stages/{$stage->id}")->assertNotFound();
    $this->post("/painel/stages/{$stage->id}/fixtures", ['double_round' => false])->assertNotFound();
    $this->post("/painel/games/{$game->id}/result", ['home_score' => 1, 'away_score' => 0])->assertNotFound();
    $this->patch("/painel/games/{$game->id}", ['status' => 'postponed'])->assertNotFound();

    expect(Stage::withoutGlobalScopes()->whereKey($stage->id)->exists())->toBeTrue();
});

it('redirects guests away from the game routes', function () {
    auth()->logout();

    $this->post('/painel/games/01abc/result', ['home_score' => 1, 'away_score' => 0])->assertRedirect(route('login'));
});

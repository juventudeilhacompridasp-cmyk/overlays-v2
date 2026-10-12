<?php

use App\Models\User;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Actions\RecordShootout;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Actions\ReopenGame;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** @return array{stage: Stage, entries: list<Entry>} */
function knockoutStage(int $teams, int $legs = 1): array
{
    $data = fixtureDivision($teams, StageType::Knockout);
    app(GenerateStageFixtures::class)->handle($data['stage'], legs: $legs);

    return $data;
}

function roundGames(string $stageId, int $round)
{
    return Game::query()->where('stage_id', $stageId)->where('round', $round)->orderBy('number')->get();
}

it('generates a four-team single-leg bracket with the final still open', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(4);

    $semis = roundGames($stage->id, 1);
    $final = roundGames($stage->id, 2)->first();

    expect(Game::query()->where('stage_id', $stage->id)->count())->toBe(3)
        ->and($semis->map(fn (Game $g) => [$g->home_entry_id, $g->away_entry_id])->all())
        ->toBe([[$entries[0]->id, $entries[3]->id], [$entries[1]->id, $entries[2]->id]])
        ->and([$final->home_entry_id, $final->away_entry_id])->toBe([null, null])
        ->and($semis[0]->next_tie_id)->toBe($final->tie_id)
        ->and([$semis[0]->next_slot, $semis[1]->next_slot])->toBe(['a', 'b'])
        ->and($final->next_tie_id)->toBeNull();
});

it('gives byes to the best seeds when the number of teams is not a power of two', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(6);

    $semis = roundGames($stage->id, 2);

    expect(Game::query()->where('stage_id', $stage->id)->count())->toBe(5)
        ->and(roundGames($stage->id, 1))->toHaveCount(2)
        ->and($semis->first()->home_entry_id)->toBe($entries[0]->id)
        ->and($semis->last()->home_entry_id)->toBe($entries[1]->id)
        ->and($semis->first()->away_entry_id)->toBeNull();
});

it('creates home-and-away legs where the lower seed hosts the first leg', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(4, 2);

    $first = roundGames($stage->id, 1)->where('tie_id', roundGames($stage->id, 1)->first()->tie_id)->sortBy('leg')->values();

    expect(Game::query()->where('stage_id', $stage->id)->count())->toBe(6)
        ->and([$first[0]->leg, $first[0]->home_entry_id, $first[0]->away_entry_id])->toBe([1, $entries[3]->id, $entries[0]->id])
        ->and([$first[1]->leg, $first[1]->home_entry_id, $first[1]->away_entry_id])->toBe([2, $entries[0]->id, $entries[3]->id]);
});

it('advances the winner of a single-leg tie to the next round', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(4);
    [$g1, $g2] = roundGames($stage->id, 1)->all();

    app(RecordQuickResult::class)->handle($g1, 2, 0);
    $final = roundGames($stage->id, 2)->first();
    expect([$final->home_entry_id, $final->away_entry_id])->toBe([$entries[0]->id, null]);

    app(RecordQuickResult::class)->handle($g2, 0, 1); // visitante (entries[2]) vence
    $final->refresh();
    expect([$final->home_entry_id, $final->away_entry_id])->toBe([$entries[0]->id, $entries[2]->id]);
});

it('refuses to finish a level knockout game without a shootout and rolls everything back', function () {
    ['stage' => $stage] = knockoutStage(4);
    $game = roundGames($stage->id, 1)->first();

    expect(fn () => app(RecordQuickResult::class)->handle($game, 1, 1))->toThrow(InvalidArgumentException::class, 'pênaltis');

    $game->refresh();
    expect($game->status)->toBe(GameStatus::Scheduled)->and($game->events()->count())->toBe(0);
});

it('decides a level game on penalties and advances the winner', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(4);
    $game = roundGames($stage->id, 1)->first();

    app(RecordQuickResult::class)->handle($game, 1, 1, null, 3, 4);

    $game->refresh();
    $final = roundGames($stage->id, 2)->first();

    expect([$game->home_penalties, $game->away_penalties])->toBe([3, 4])
        ->and($final->home_entry_id)->toBe($entries[3]->id);
});

it('rejects invalid shootouts', function () {
    ['stage' => $stage] = knockoutStage(4);
    $game = roundGames($stage->id, 1)->first();

    expect(fn () => app(RecordQuickResult::class)->handle($game, 0, 0, null, 3, 3))->toThrow(InvalidArgumentException::class, 'empatada')
        ->and(fn () => app(RecordShootout::class)->handle($game, 3, null))->toThrow(InvalidArgumentException::class, 'duas equipes')
        ->and(fn () => app(RecordShootout::class)->handle(Game::factory()->create(), 3, 2))->toThrow(InvalidArgumentException::class, 'mata-mata');
});

it('resolves a two-leg tie by aggregate only after both legs', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(4, 2);
    $legs = roundGames($stage->id, 1)->where('tie_id', roundGames($stage->id, 1)->first()->tie_id)->sortBy('leg')->values();
    $final = roundGames($stage->id, 2)->sortBy('leg')->values();

    app(RecordQuickResult::class)->handle($legs[0], 0, 1); // ida: entries[0] (visitante) vence por 1
    expect($final[0]->fresh()->away_entry_id)->toBeNull()->and($final[0]->fresh()->home_entry_id)->toBeNull();

    app(RecordQuickResult::class)->handle($legs[1], 1, 0); // volta: entries[0] vence por 1 → agregado 2 x 0
    $leg1 = $final[0]->fresh();
    $leg2 = $final[1]->fresh();

    // Lado a (melhor seed) = entries[0]: na ida da final é visitante, na volta é mandante.
    expect($leg1->away_entry_id)->toBe($entries[0]->id)->and($leg2->home_entry_id)->toBe($entries[0]->id);
});

it('uses the last leg shootout when the aggregate is level', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(4, 2);
    $legs = roundGames($stage->id, 1)->where('tie_id', roundGames($stage->id, 1)->first()->tie_id)->sortBy('leg')->values();

    app(RecordQuickResult::class)->handle($legs[0], 1, 0);
    expect(fn () => app(RecordQuickResult::class)->handle($legs[1], 1, 0))->toThrow(InvalidArgumentException::class, 'pênaltis');

    // Volta: mandante é entries[0]; agregado 1x1 → pênaltis do jogo de volta decidem.
    app(RecordQuickResult::class)->handle($legs[1], 1, 0, null, 2, 4);
    $final = roundGames($stage->id, 2)->sortBy('leg')->values();

    // Perdeu nos pênaltis quem era mandante na volta (entries[0]): entries[3] ocupa o lado a da final,
    // que na ida da final é o visitante.
    expect($final[0]->fresh()->away_entry_id)->toBe($entries[3]->id);
});

it('takes the winner out of the next round when the game is reopened', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(4);
    $game = roundGames($stage->id, 1)->first();

    app(RecordQuickResult::class)->handle($game, 3, 0);
    expect(roundGames($stage->id, 2)->first()->home_entry_id)->toBe($entries[0]->id);

    app(ReopenGame::class)->handle($game->fresh());
    expect(roundGames($stage->id, 2)->first()->home_entry_id)->toBeNull();
});

it('does not reopen a game whose winner is already playing the next round', function () {
    ['stage' => $stage] = knockoutStage(4);
    [$g1, $g2] = roundGames($stage->id, 1)->all();

    app(RecordQuickResult::class)->handle($g1, 1, 0);
    app(RecordQuickResult::class)->handle($g2, 1, 0);
    app(RecordQuickResult::class)->handle(roundGames($stage->id, 2)->first(), 2, 0);

    expect(fn () => app(ReopenGame::class)->handle($g1->fresh()))->toThrow(InvalidArgumentException::class, 'seguinte já começou');
});

it('advances the winner of a walkover', function () {
    ['stage' => $stage, 'entries' => $entries] = knockoutStage(4);
    $game = roundGames($stage->id, 1)->first();

    app(RegisterWalkover::class)->handle($game, $game->awayEntry);

    expect(roundGames($stage->id, 2)->first()->home_entry_id)->toBe($entries[3]->id);
});

it('does not touch the league games', function () {
    ['stage' => $stage] = fixtureDivision(3);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();

    app(RecordQuickResult::class)->handle($game, 1, 1);

    expect($game->fresh()->status)->toBe(GameStatus::Finished)->and($game->tie_id)->toBeNull();
});

it('needs at least two teams, a valid number of legs and no existing games', function () {
    ['stage' => $stage] = fixtureDivision(1, StageType::Knockout);

    expect(fn () => app(GenerateStageFixtures::class)->handle($stage))->toThrow(InvalidArgumentException::class, 'pelo menos duas');

    ['stage' => $ok] = fixtureDivision(4, StageType::Knockout);

    expect(fn () => app(GenerateStageFixtures::class)->handle($ok, legs: 3))->toThrow(InvalidArgumentException::class, 'ida e volta');

    app(GenerateStageFixtures::class)->handle($ok);

    expect(fn () => app(GenerateStageFixtures::class)->handle($ok))->toThrow(InvalidArgumentException::class, 'já tem jogos');
});

it('generates and shows the bracket through the panel with round names and penalties', function () {
    $this->actingAs(User::factory()->create());
    ['stage' => $stage] = fixtureDivision(4, StageType::Knockout);

    $this->post("/painel/stages/{$stage->id}/fixtures", ['double_round' => false, 'legs' => 2])
        ->assertSessionHas('success', 'Tabela gerada com 6 jogos.');

    $this->get("/painel/stages/{$stage->id}")->assertInertia(fn ($page) => $page
        ->where('generation.can_generate', false)
        ->has('games', 6)
        ->where('games.0.round_label', 'Semifinal')
        ->where('games.0.legs', 2)
        ->where('games.5.round_label', 'Final'));

    $game = roundGames($stage->id, 1)->sortBy('leg')->first();
    $this->post("/painel/games/{$game->id}/result", ['home_score' => 1, 'away_score' => 1, 'home_penalties' => 5, 'away_penalties' => 4])
        ->assertSessionHas('success', 'Resultado lançado.');

    expect($game->fresh()->home_penalties)->toBe(5);
});

it('records a shootout from the sheet and keeps kicks out of the event list', function () {
    $this->actingAs(User::factory()->create());
    ['stage' => $stage] = knockoutStage(4);
    $game = roundGames($stage->id, 1)->first();

    $this->post("/painel/games/{$game->id}/shootout", ['home_penalties' => 4, 'away_penalties' => 2])->assertSessionHas('success', 'Pênaltis registrados.');

    $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page
        ->where('game.knockout', true)
        ->where('game.home_penalties', 4)
        ->where('game.away_penalties', 2)
        ->has('events', 0));

    $this->post("/painel/games/{$game->id}/shootout", ['home_penalties' => 3, 'away_penalties' => 3])->assertSessionHas('error', 'A disputa de pênaltis não pode terminar empatada.');
    $this->post("/painel/games/{$game->id}/shootout", [])->assertSessionHas('success');

    expect($game->fresh()->home_penalties)->toBeNull();
});

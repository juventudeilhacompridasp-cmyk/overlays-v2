<?php

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordShootout;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Matches\Support\ShootoutView;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function shootoutGame(): Game
{
    ['stage' => $stage] = fixtureDivision(4, StageType::Knockout);
    app(GenerateStageFixtures::class)->handle($stage);

    return Game::query()->where('stage_id', $stage->id)->where('round', 1)->orderBy('number')->firstOrFail();
}

it('records the shootout kick by kick, alternating the teams and closing it when there is a winner', function () {
    $this->actingAs(panelUser(Role::Operator));
    $game = shootoutGame();
    $kick = fn (string $side, bool $scored) => $this->post("/painel/games/{$game->id}/shootout/kick", ['side' => $side, 'scored' => $scored]);

    $kick('home', true)->assertSessionHasNoErrors();
    $kick('home', true)->assertSessionHas('error', 'Não é a vez desta equipe: as cobranças se alternam.');

    foreach ([['away', false], ['home', true], ['away', false], ['home', true], ['away', false]] as [$side, $scored]) {
        $kick($side, $scored)->assertSessionHasNoErrors();
    }

    $fresh = $game->fresh();
    expect([$fresh->home_penalties, $fresh->away_penalties])->toBe([3, 0])
        ->and(ShootoutView::for($fresh))->toMatchArray(['decided' => true, 'winner' => 'home', 'next' => null, 'by_kick' => true, 'home' => [true, true, true], 'away' => [false, false, false]]);

    $kick('away', true)->assertSessionHas('error', 'A disputa já está decidida. Desfaça a última cobrança para corrigir.');

    // Desfazer a última (perdida) reabre a disputa: o visitante ainda tem cobranças.
    $this->post("/painel/games/{$game->id}/shootout/undo")->assertSessionHasNoErrors();
    expect(ShootoutView::for($game->fresh()))->toMatchArray(['decided' => false, 'next' => 'away', 'away' => [false, false]]);

    $kick('away', false)->assertSessionHasNoErrors();
    $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page
        ->where('game.shootout.winner', 'home')->where('game.shootout.by_kick', true)->where('game.home_penalties', 3)->has('events', 0));

    $this->post("/painel/games/{$game->id}/finish")->assertSessionHasNoErrors();
    expect($game->fresh()->status->isDecided())->toBeTrue();
});

it('lets a missed first kick count as a kick without a goal and undoes down to an empty shootout', function () {
    $this->actingAs(panelUser(Role::Operator));
    $game = shootoutGame();

    $this->post("/painel/games/{$game->id}/shootout/undo")->assertSessionHas('error', 'Não há cobrança para desfazer.');
    $this->post("/painel/games/{$game->id}/shootout/kick", ['side' => 'away', 'scored' => false])->assertSessionHasNoErrors();

    expect([$game->fresh()->home_penalties, $game->fresh()->away_penalties])->toBe([0, 0])
        ->and(ShootoutView::for($game->fresh()))->toMatchArray(['first' => 'away', 'next' => 'home']);

    $this->post("/painel/games/{$game->id}/shootout/undo")->assertSessionHasNoErrors();
    expect($game->fresh()->home_penalties)->toBeNull()->and(ShootoutView::for($game->fresh()))->toBeNull();
});

it('keeps the total typed by hand apart from the kick by kick shootout', function () {
    $this->actingAs(panelUser(Role::Operator));
    $game = shootoutGame();
    app(RecordShootout::class)->handle($game, 4, 2);

    expect(ShootoutView::for($game->fresh()))->toMatchArray(['by_kick' => false, 'decided' => true, 'winner' => 'home', 'next' => null]);

    $this->post("/painel/games/{$game->id}/shootout/kick", ['side' => 'home', 'scored' => true])->assertSessionHas('error');
    $this->post("/painel/games/{$game->id}/shootout/undo")->assertSessionHas('error');

    // Salvar o total em branco limpa a disputa e libera o lançamento cobrança a cobrança.
    $this->post("/painel/games/{$game->id}/shootout", [])->assertSessionHas('success');
    $this->post("/painel/games/{$game->id}/shootout/kick", ['side' => 'home', 'scored' => true])->assertSessionHasNoErrors();
    expect($game->fresh()->home_penalties)->toBe(1);
});

it('puts only sides and results of the shootout in the public snapshot', function () {
    $this->actingAs(panelUser(Role::Operator));
    $game = shootoutGame();

    expect(PublicGameSnapshot::for($game)['shootout'])->toBeNull();

    $this->post("/painel/games/{$game->id}/shootout/kick", ['side' => 'home', 'scored' => true]);
    $this->post("/painel/games/{$game->id}/shootout/kick", ['side' => 'away', 'scored' => false]);

    $shootout = PublicGameSnapshot::for($game->fresh())['shootout'];
    expect($shootout)->toMatchArray(['home' => [true], 'away' => [false], 'first' => 'home', 'next' => 'home', 'decided' => false, 'rounds' => 5])
        ->and(json_encode($shootout))->not->toContain((string) $game->home_entry_id)->not->toContain((string) $game->away_entry_id);
});

it('refuses kicks outside knockout games and from people who cannot operate games', function () {
    ['game' => $league] = sheetGame();
    $this->actingAs(panelUser(Role::Operator));
    $this->post("/painel/games/{$league->id}/shootout/kick", ['side' => 'home', 'scored' => true])->assertSessionHas('error', 'Só jogos de mata-mata têm disputa de pênaltis.');

    $game = shootoutGame();
    $this->post("/painel/games/{$game->id}/shootout/kick", ['side' => 'meio', 'scored' => true])->assertSessionHasErrors('side');

    $this->actingAs(panelUser(Role::Viewer));
    $this->post("/painel/games/{$game->id}/shootout/kick", ['side' => 'home', 'scored' => true]);
    expect($game->fresh()->home_penalties)->toBeNull();
});

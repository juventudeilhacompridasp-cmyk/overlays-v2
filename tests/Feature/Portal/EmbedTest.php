<?php

use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'name' => 'Liga Local']));
});

/** @return array{game: Game, division: Division, season: Season} */
function embedFixture(bool $publish = true): array
{
    ['stage' => $stage] = fixtureDivision(3);
    app(GenerateStageFixtures::class)->handle($stage);
    $games = Game::query()->where('stage_id', $stage->id)->get();
    app(RecordQuickResult::class)->handle($games[0], 2, 0);

    if ($publish) {
        publishSeason($games[0]);
    }

    return ['game' => $games[0]->fresh(), 'division' => $stage->division, 'season' => $stage->division->season];
}

describe('widgets', function () {
    it('serves the live score of a published game', function () {
        ['game' => $game] = embedFixture();

        $response = $this->get("/embed/jogo/{$game->id}?tema=escuro&cor=%23ff6600")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Embed/Game')
            ->where('options.dark', true)
            ->where('options.accent', '#ff6600')
            ->where('snapshot.id', $game->id)
            ->where('snapshot.home_score', 2)
            ->where('portal_url', route('public.games.show', $game->id)));

        $json = json_encode($response->original->getData()['page']['props']);
        expect($json)->not->toContain('yellow')->not->toContain('birth');
    });

    it('serves the standings of the division with a row limit', function () {
        ['division' => $division] = embedFixture();

        $this->get("/embed/classificacao/{$division->id}?linhas=2")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Embed/Standings')
            ->has('tables', 1)
            ->has('tables.0.rows', 2)
            ->where('tables.0.rows.0.position', 1));

        $this->get("/embed/classificacao/{$division->id}?linhas=999")->assertInertia(fn ($page) => $page->has('tables.0.rows', 3));
    });

    it('lists live, upcoming and finished games of the season', function () {
        ['season' => $season, 'game' => $played] = embedFixture();
        $other = Game::query()->where('division_id', $played->division_id)->where('id', '!=', $played->id)->first();
        $other->update(['scheduled_at' => now()->addDay()]);

        $this->get("/embed/jogos/{$season->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Embed/Games')
            ->has('recent', 1)
            ->where('recent.0.id', $played->id)
            ->has('upcoming', 1)
            ->where('upcoming.0.id', $other->id)
            ->where('live', []));
    });

    it('ignores an invalid accent color and falls back to the theme color', function () {
        ['game' => $game] = embedFixture();

        $this->get("/embed/jogo/{$game->id}?cor=red;}body{display:none")->assertInertia(fn ($page) => $page->where('options.accent', '#059669')->where('options.dark', false));
    });

    it('hides draft seasons and requires the feature', function () {
        ['game' => $game, 'division' => $division, 'season' => $season] = embedFixture(publish: false);

        $this->get("/embed/jogo/{$game->id}")->assertNotFound();
        $this->get("/embed/classificacao/{$division->id}")->assertNotFound();
        $this->get("/embed/jogos/{$season->id}")->assertNotFound();

        publishSeason($game);
        $this->get("/embed/jogo/{$game->id}")->assertOk();

        CurrentOrganization::get()->update(['plan' => 'basic']);
        $this->get("/embed/jogo/{$game->id}")->assertNotFound();
    });
});

describe('framing', function () {
    it('lets widgets be embedded anywhere and forbids everything else from being framed', function () {
        ['game' => $game] = embedFixture();

        $embed = $this->get("/embed/jogo/{$game->id}");
        expect($embed->headers->get('Content-Security-Policy'))->toBe('frame-ancestors *')
            ->and($embed->headers->has('X-Frame-Options'))->toBeFalse();

        foreach (['/', "/jogos/{$game->id}", '/login'] as $path) {
            $response = $this->get($path);
            expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
                ->and($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'self'");
        }
    });
});

describe('panel generator', function () {
    it('lists seasons, divisions and open games for any panel member', function () {
        ['game' => $game, 'season' => $season] = embedFixture();
        $open = Game::query()->where('division_id', $game->division_id)->where('id', '!=', $game->id)->first();
        $this->actingAs(panelUser(Role::Viewer));

        $this->get('/painel/widgets')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Panel/Embeds/Index')
            ->where('base_url', url('/embed'))
            ->where('seasons.0.id', $season->id)
            ->where('seasons.0.published', true)
            ->has('seasons.0.divisions', 1)
            ->where('games', fn ($games) => collect($games)->pluck('id')->contains($open->id) && ! collect($games)->pluck('id')->contains($game->id)));
    });

    it('is only available with the feature', function () {
        $this->actingAs(panelUser(Role::Admin));
        CurrentOrganization::get()->update(['plan' => 'basic']);

        $this->get('/painel/widgets')->assertForbidden();
    });
});

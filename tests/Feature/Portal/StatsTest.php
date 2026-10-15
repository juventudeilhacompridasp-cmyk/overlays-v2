<?php

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'name' => 'Liga Local']));
});

/** Pontos corridos com 3 equipes: A x B 4-0, A x C 1-1, B x C 0-2 (os confrontos vêm do sorteio da fase, então usa as chaves). */
function statsSeason(): array
{
    ['stage' => $stage, 'entries' => $entries] = fixtureDivision(3);
    app(GenerateStageFixtures::class)->handle($stage);
    publishSeason(Game::query()->where('stage_id', $stage->id)->firstOrFail());

    $games = Game::query()->where('stage_id', $stage->id)->get();
    $score = fn (Game $game, int $home, int $away) => app(RecordQuickResult::class)->handle($game, $home, $away);

    // por par de equipes, independente de quem é mandante
    $by = fn (string $a, string $b) => $games->first(fn (Game $game) => in_array($a, [$game->home_entry_id, $game->away_entry_id], true) && in_array($b, [$game->home_entry_id, $game->away_entry_id], true));
    [$a, $b, $c] = array_map(fn ($entry) => $entry->id, $entries);

    $ab = $by($a, $b);
    $score($ab, $ab->home_entry_id === $a ? 4 : 0, $ab->home_entry_id === $a ? 0 : 4);
    $ac = $by($a, $c);
    $score($ac, 1, 1);
    $bc = $by($b, $c);
    $score($bc, $bc->home_entry_id === $c ? 2 : 0, $bc->home_entry_id === $c ? 0 : 2);

    $season = $stage->division->season;

    return ['season' => $season, 'competition' => $season->competition, 'entries' => $entries, 'division' => $stage->division];
}

it('summarizes the division from finished games', function () {
    ['season' => $season, 'competition' => $competition] = statsSeason();

    $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}/estatisticas")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Portal/Stats')
        ->where('unit', 'gols')
        ->where('divisions.0.summary.games', 3)
        ->where('divisions.0.summary.total', 8)
        ->where('divisions.0.summary.average', 2.67)
        ->where('divisions.0.summary.draws', 1)
        ->has('divisions.0.teams', 3)
        ->has('divisions.0.biggest_wins', 2)
        ->where('divisions.0.biggest_wins.0.home_score', fn ($score) => in_array($score, [4, 0], true))
        ->where('divisions.0.highest_scoring.0.home_score', fn ($score) => in_array($score, [4, 0], true)));
});

it('computes attack, defense, form and rates per team', function () {
    ['season' => $season, 'entries' => $entries] = statsSeason();
    [$a, $b, $c] = array_map(fn ($entry) => $entry->team_id, $entries);

    $response = $this->getJson("/api/v1/seasons/{$season->id}/stats")->assertOk();
    $teams = collect($response->json('data.divisions.0.teams'))->keyBy('team.id');

    expect($teams[$a])->toMatchArray(['played' => 2, 'wins' => 1, 'draws' => 1, 'losses' => 0, 'for' => 5, 'against' => 1, 'avg_for' => 2.5, 'win_rate' => 50.0])
        ->and($teams[$a]['form'])->toHaveCount(2)
        ->and($teams[$b])->toMatchArray(['played' => 2, 'wins' => 0, 'losses' => 2, 'for' => 0, 'against' => 6, 'clean_sheets' => 0])
        ->and($teams[$c])->toMatchArray(['played' => 2, 'wins' => 1, 'draws' => 1, 'for' => 3, 'against' => 1, 'clean_sheets' => 1]);

    expect($response->json('data.divisions.0.best_attack.team.id'))->toBe($a)
        ->and($response->json('data.divisions.0.best_defense.team.id'))->toBeIn([$a, $c]);
});

it('keeps only the last five results as form and ignores walkovers and unfinished games', function () {
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    publishSeason($game);
    app(RegisterWalkover::class)->handle($game, $game->homeEntry);
    $season = $stage->division->season;

    $this->getJson("/api/v1/seasons/{$season->id}/stats")->assertOk()
        ->assertJsonPath('data.divisions.0.summary.games', 0)
        ->assertJsonPath('data.divisions.0.teams', [])
        ->assertJsonPath('data.divisions.0.best_attack', null);
});

it('uses the unit of the sport', function () {
    ['season' => $season, 'competition' => $competition] = statsSeason();
    $competition->update(['sport' => Sport::Basketball]);

    $this->getJson("/api/v1/seasons/{$season->id}/stats")->assertJsonPath('data.unit', 'pontos')->assertJsonPath('data.unit_singular', 'ponto');
});

it('hides draft seasons and exposes no personal or disciplinary data', function () {
    ['season' => $season, 'competition' => $competition] = statsSeason();

    $json = $this->getJson("/api/v1/seasons/{$season->id}/stats")->getContent();
    expect($json)->not->toContain('yellow')->not->toContain('red_card')->not->toContain('birth')->not->toContain('document');

    $season->update(['status' => 'draft']);
    $this->getJson("/api/v1/seasons/{$season->id}/stats")->assertNotFound();
    $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}/estatisticas")->assertNotFound();
});

it('is linked from the season page and listed in the sitemap', function () {
    ['season' => $season, 'competition' => $competition] = statsSeason();

    $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}")->assertInertia(fn ($page) => $page->where('stats_url', route('portal.stats', [$competition->slug, $season->id])));
    $this->get('/sitemap.xml')->assertSee("/competicoes/{$competition->slug}/edicoes/{$season->id}/estatisticas", false);
});

it('turns the stats page off: 404, no link and out of the sitemap', function () {
    ['season' => $season, 'competition' => $competition] = statsSeason();
    CurrentOrganization::get()->update(['settings' => ['portal' => ['season_stats' => false]]]);

    $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}/estatisticas")->assertNotFound();
    $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}")->assertInertia(fn ($page) => $page->where('stats_url', null));
    $this->get('/sitemap.xml')->assertDontSee("/competicoes/{$competition->slug}/edicoes/{$season->id}/estatisticas", false);
});

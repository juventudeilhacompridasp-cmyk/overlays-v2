<?php

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Support\JsonLd;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** @return array{game: Game, home: Entry} */
function jsonLdGame(): array
{
    $data = sheetGame();
    publishSeason($data['game']);

    return $data;
}

it('describes a scheduled game as a sports event with both teams, date and status', function () {
    ['game' => $game, 'home' => $home] = jsonLdGame();
    $game->update(['scheduled_at' => '2026-11-01 16:00:00']);

    $data = JsonLd::game($game->fresh());

    expect($data['@context'])->toBe('https://schema.org')
        ->and($data['@type'])->toBe('SportsEvent')
        ->and($data['name'])->toBe($home->team->name.' x '.$game->awayEntry->team->name)
        ->and($data['eventStatus'])->toBe('https://schema.org/EventScheduled')
        ->and($data['homeTeam']['@type'])->toBe('SportsTeam')
        ->and($data['homeTeam']['url'])->toBe(route('portal.team', $home->team_id))
        ->and($data['url'])->toBe(route('public.games.show', $game->getKey()))
        ->and($data['startDate'])->toStartWith('2026-11-01T')
        ->and($data['description'])->not->toContain('Resultado');
});

it('adds the result once decided and maps postponed and cancelled games', function () {
    ['game' => $game, 'home' => $home] = jsonLdGame();
    $game->update(['status' => GameStatus::Finished, 'home_score' => 2, 'away_score' => 1]);

    expect(JsonLd::game($game->fresh())['description'])->toContain("Resultado: {$home->team->name} 2 x 1");

    $game->update(['status' => GameStatus::Postponed]);
    expect(JsonLd::game($game->fresh())['eventStatus'])->toBe('https://schema.org/EventPostponed');

    $game->update(['status' => GameStatus::Cancelled]);
    expect(JsonLd::game($game->fresh())['eventStatus'])->toBe('https://schema.org/EventCancelled');
});

it('has nothing for a game whose teams are not defined yet', function () {
    ['game' => $game] = jsonLdGame();
    $game->update(['home_entry_id' => null]);

    expect(JsonLd::game($game->fresh()))->toBeNull();
});

it('puts the structured data in the game page, the team page and the season page', function () {
    ['game' => $game, 'home' => $home] = jsonLdGame();
    $season = $game->division->season;

    $this->get(route('public.games.show', $game->getKey()))->assertOk()->assertInertia(fn ($page) => $page->where('json_ld.@type', 'SportsEvent')->where('json_ld.homeTeam.name', $home->team->name));
    $this->get("/equipes/{$home->team_id}")->assertOk()->assertInertia(fn ($page) => $page->where('seo.json_ld.@type', 'SportsTeam')->where('seo.json_ld.name', $home->team->name));
    $this->get(route('portal.season', [$season->competition->slug, $season->getKey()]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('seo.json_ld.@type', 'SportsEvent')->has('seo.json_ld.subEvent', 1)->where('seo.json_ld.subEvent.0.@type', 'SportsEvent'));
});

it('never leaks athletes, cards, lineups or ids', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = jsonLdGame();
    Person::query()->whereKey($hp[0])->update(['name' => 'Rafael Souza']);
    Team::query()->whereKey($home->team_id)->update(['city' => 'Ilhabela']);
    $season = $game->division->season;

    $json = json_encode([JsonLd::game($game->fresh()), JsonLd::teamPage($home->team->fresh()), JsonLd::season($season->competition, $season, [$game->fresh()])]);

    expect($json)->not->toContain('Rafael')->not->toContain($hp[0])->not->toContain('document')->not->toContain('birth')
        ->and($json)->toContain('Ilhabela');
});

it('does not publish structured data for draft seasons', function () {
    ['game' => $game] = sheetGame();

    $this->get(route('public.games.show', $game->getKey()))->assertNotFound();
});

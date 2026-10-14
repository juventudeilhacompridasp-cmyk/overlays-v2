<?php

use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'name' => 'Liga Local']));
});

/** @return array{game: Game, competition: Competition, season: Season} */
function publishedGame(): array
{
    $data = sheetGame();
    publishSeason($data['game']);
    $season = Season::query()->findOrFail($data['game']->division->season_id);

    return [...$data, 'competition' => $season->competition, 'season' => $season];
}

it('shows the home with published competitions, live, upcoming and recent games and hides drafts', function () {
    ['game' => $game, 'competition' => $competition] = publishedGame();
    $draft = Competition::factory()->create(['name' => 'Copa Secreta']);
    Season::factory()->create(['competition_id' => $draft->id]);
    $game->forceFill(['status' => GameStatus::InProgress, 'started_at' => now()])->save();

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Portal/Home')
        ->where('theme.name', 'Liga Local')
        ->where('seo.title', 'Liga Local')
        ->has('competitions', 1)
        ->where('competitions.0.name', $competition->name)
        ->has('live', 1)
        ->where('live.0.id', $game->id)
        ->has('upcoming', 0)
        ->has('recent', 0));
});

it('lists upcoming and recent games', function () {
    ['game' => $game] = publishedGame();
    $game->forceFill(['scheduled_at' => now()->addDays(2)])->save();

    $this->get('/')->assertInertia(fn ($page) => $page->has('upcoming', 1)->where('upcoming.0.id', $game->id));

    app(RecordQuickResult::class)->handle($game->fresh(), 2, 1);

    $this->get('/')->assertInertia(fn ($page) => $page->has('recent', 1)->where('recent.0.home_score', 2)->has('upcoming', 0));
});

it('redirects a competition to its latest published season when the competition page is off', function () {
    ['competition' => $competition, 'season' => $season] = publishedGame();
    $newer = Season::factory()->create(['competition_id' => $competition->id, 'name' => '2099', 'year' => 2099, 'status' => SeasonStatus::Active]);
    Season::factory()->create(['competition_id' => $competition->id, 'name' => '2100', 'year' => 2100, 'status' => SeasonStatus::Draft]);

    $organization = CurrentOrganization::get();
    $organization->forceFill(['settings' => ['portal' => ['competition_page' => false]]])->save();

    $this->get("/competicoes/{$competition->slug}")->assertRedirect(route('portal.season', [$competition->slug, $newer->id]));

    $empty = Competition::factory()->create();
    $this->get("/competicoes/{$empty->slug}")->assertNotFound();
});

it('shows a season with standings, rounds and no disciplinary data', function () {
    ['game' => $game, 'competition' => $competition, 'season' => $season, 'home' => $home, 'hp' => $hp] = publishedGame();
    app(RecordGameEvent::class)->handle($game, GameEventType::RedCard, $home->id, $hp[0]);
    app(FinishGame::class)->handle($game->fresh());

    $response = $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Portal/Season')
        ->where('season.id', $season->id)
        ->has('divisions', 1)
        ->has('divisions.0.stages.0.tables.0.rows', 2)
        ->where('divisions.0.stages.0.rounds.0.label', 'Rodada 1')
        ->where('divisions.0.stages.0.rounds.0.games.0.status', 'finished')
        ->where('seo.url', route('portal.season', [$competition->slug, $season->id])));

    // As rotas compartilhadas (Ziggy) têm nomes como "documents"; o que importa é o conteúdo da página.
    $props = $response->original->getData()['page']['props'];
    unset($props['ziggy']);
    $json = json_encode($props);
    expect($json)->not->toContain('yellow')->not->toContain('red_card')->not->toContain('document')->not->toContain('birth')->not->toContain('person_id');
});

it('shows knockout rounds with their names', function () {
    ['stage' => $stage] = fixtureDivision(4, StageType::Knockout);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    publishSeason($game);
    $season = Season::query()->findOrFail($game->division->season_id);

    $this->get("/competicoes/{$season->competition->slug}/edicoes/{$season->id}")->assertInertia(fn ($page) => $page
        ->where('divisions.0.stages.0.knockout', true)
        ->has('divisions.0.stages.0.tables', 0)
        ->where('divisions.0.stages.0.rounds.0.label', 'Semifinal')
        ->where('divisions.0.stages.0.rounds.1.label', 'Final'));
});

it('hides unpublished seasons and mismatched competitions', function () {
    $data = sheetGame();
    $season = Season::query()->findOrFail($data['game']->division->season_id);
    $competition = $season->competition;

    $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}")->assertNotFound();
    $this->get('/jogos/'.$data['game']->id)->assertNotFound();
    $this->getJson('/jogos/'.$data['game']->id.'/estado')->assertNotFound();

    publishSeason($data['game']);
    $other = Competition::factory()->create();

    $this->get("/competicoes/{$other->slug}/edicoes/{$season->id}")->assertNotFound();
    $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}")->assertOk();
});

it('credits top scorers with public-safe names, ignoring own goals', function () {
    ['game' => $game, 'competition' => $competition, 'season' => $season, 'home' => $home, 'away' => $away, 'hp' => $hp, 'ap' => $ap] = publishedGame();
    Person::query()->whereKey($hp[0])->update(['name' => 'Rafael Souza Lima', 'nickname' => 'Rafa', 'birth_date' => '1995-03-10']);
    Person::query()->whereKey($hp[1])->update(['name' => 'João Pedro Santos', 'nickname' => 'Joãozinho', 'birth_date' => now()->subYears(15)->toDateString()]);
    Person::query()->whereKey($hp[2])->update(['name' => 'Sem Idade Conhecida', 'birth_date' => null]);

    $record = app(RecordGameEvent::class);
    $record->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 5);
    $record->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[0], minute: 25);
    $record->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[1], minute: 30);
    $record->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[2], minute: 31);
    $record->handle($game->fresh(), GameEventType::OwnGoal, $away->id, $ap[0], minute: 40);

    $this->get("/competicoes/{$competition->slug}/edicoes/{$season->id}")->assertInertia(fn ($page) => $page
        ->has('divisions.0.scorers', 3)
        ->where('divisions.0.scorers.0', ['name' => 'Rafa', 'team' => $home->team->name, 'goals' => 2])
        ->where('divisions.0.scorers.1.name', fn ($name) => in_array($name, ['João S.', 'Sem C.'], true)));

    $names = collect([PublicName::of(Person::query()->find($hp[1])), PublicName::of(Person::query()->find($hp[2]))]);
    expect($names->all())->toContain('João S.')->toContain('Sem C.');
});

it('abbreviates names of minors and of people with unknown age, and keeps adults as they are', function () {
    $adult = new Person(['name' => 'Maria da Silva', 'nickname' => 'Mari', 'birth_date' => now()->subYears(30)]);
    $adultNoNick = new Person(['name' => 'Carlos Alberto Souza', 'nickname' => null, 'birth_date' => now()->subYears(40)]);
    $minor = new Person(['name' => 'Pedro Henrique Almeida', 'nickname' => 'Pedrinho', 'birth_date' => now()->subYears(12)]);
    $unknown = new Person(['name' => 'Ana', 'nickname' => 'Aninha', 'birth_date' => null]);
    $turning18 = new Person(['name' => 'Bruno Lima', 'nickname' => 'Bru', 'birth_date' => now()->subYears(18)]);

    expect(PublicName::of($adult))->toBe('Mari')
        ->and(PublicName::of($adultNoNick))->toBe('Carlos Alberto Souza')
        ->and(PublicName::of($minor))->toBe('Pedro A.')
        ->and(PublicName::of($unknown))->toBe('Ana')
        ->and(PublicName::of($turning18))->toBe('Bru')
        ->and(PublicName::of(null))->toBeNull();
});

it('shows a team page only for teams in published seasons and never the roster', function () {
    ['game' => $game, 'home' => $home] = publishedGame();
    Team::query()->whereKey($home->team_id)->update(['city' => 'Ilhabela']);
    $other = Team::factory()->create();

    $this->get("/equipes/{$home->team_id}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Portal/Team')
        ->where('team.name', $home->team->name)
        ->where('team.city', 'Ilhabela')
        ->has('participations', 1)
        ->has('games', 1)
        ->where('games.0.id', $game->id)
        ->missing('roster')
        ->missing('players'));

    $this->get("/equipes/{$other->id}")->assertNotFound();
});

it('does not show a team that only plays in a draft season', function () {
    ['home' => $home] = sheetGame();

    $this->get("/equipes/{$home->team_id}")->assertNotFound();
});

it('serves the sitemap and robots.txt with only public pages', function () {
    ['competition' => $competition, 'season' => $season, 'home' => $home] = publishedGame();
    $draft = Season::factory()->create(['competition_id' => $competition->id, 'name' => 'Rascunho', 'year' => 2090]);

    $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect($sitemap)->toContain(route('portal.home'))
        ->toContain(route('portal.season', [$competition->slug, $season->id]))
        ->toContain(route('portal.team', $home->team_id))
        ->not->toContain($draft->id)
        ->not->toContain('/painel');

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /painel')->assertSee('Disallow: /overlay')->assertSee('Sitemap: '.route('portal.sitemap'));
});

it('reads the theme from the organization settings and falls back on bad colors', function () {
    CurrentOrganization::get()->update(['settings' => ['theme' => ['primary' => '#ff6600', 'tagline' => 'A bola rola aqui']]]);
    $this->get('/')->assertInertia(fn ($page) => $page->where('theme', ['name' => 'Liga Local', 'primary' => '#ff6600', 'tagline' => 'A bola rola aqui', 'logo_url' => null, 'white_label' => true, 'platform_name' => config('app.name')]));

    CurrentOrganization::get()->update(['settings' => ['theme' => ['primary' => 'javascript:alert(1)']]]);
    $this->get('/')->assertInertia(fn ($page) => $page->where('theme.primary', '#059669')->where('theme.tagline', null));
});

describe('public API v1', function () {
    it('lists competitions with only published seasons', function () {
        ['competition' => $competition, 'season' => $season] = publishedGame();
        Season::factory()->create(['competition_id' => $competition->id, 'name' => 'Rascunho']);

        $this->getJson('/api/v1/competitions')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $competition->slug)
            ->assertJsonCount(1, 'data.0.seasons')
            ->assertJsonPath('data.0.seasons.0.url', route('api.v1.seasons.show', $season->id));
    });

    it('returns a season with the same public data as the portal', function () {
        ['season' => $season] = publishedGame();

        $this->getJson("/api/v1/seasons/{$season->id}")->assertOk()
            ->assertJsonPath('data.season.id', $season->id)
            ->assertJsonCount(1, 'data.divisions')
            ->assertJsonCount(2, 'data.divisions.0.stages.0.tables.0.rows');
    });

    it('returns the public snapshot of a game and hides drafts', function () {
        ['game' => $game] = publishedGame();

        $this->getJson("/api/v1/games/{$game->id}")->assertOk()->assertJsonPath('data.id', $game->id)->assertJsonPath('data.clock.state', 'not_started');

        $draft = sheetGame()['game'];
        $this->getJson("/api/v1/games/{$draft->id}")->assertNotFound();
        $this->getJson('/api/v1/seasons/'.$draft->division->season_id)->assertNotFound();
        $this->getJson('/api/v1/games/inexistente')->assertNotFound();
    });

    it('does not need a login and does not expose the panel or personal data', function () {
        ['game' => $game, 'season' => $season] = publishedGame();

        $json = $this->getJson("/api/v1/seasons/{$season->id}")->assertOk()->getContent().$this->getJson("/api/v1/games/{$game->id}")->getContent();

        expect($json)->not->toContain('document')->not->toContain('birth')->not->toContain('/painel');
    });
});

describe('panel: portal identity', function () {
    it('lets administrators change the name, color and tagline', function () {
        $this->actingAs(panelUser(Role::Admin));

        $this->get('/painel/organization')->assertOk()->assertInertia(fn ($page) => $page->component('Panel/Organization/Edit')->where('theme.name', 'Liga Local'));

        $this->patch('/painel/organization', ['name' => 'Liga das Ilhas', 'primary' => '#1d4ed8', 'tagline' => 'Futebol de base'])->assertSessionHas('success', 'Portal atualizado.');

        $this->get('/')->assertInertia(fn ($page) => $page->where('theme', ['name' => 'Liga das Ilhas', 'primary' => '#1d4ed8', 'tagline' => 'Futebol de base', 'logo_url' => null, 'white_label' => true, 'platform_name' => config('app.name')]));
    });

    it('validates the color and refuses non-administrators', function () {
        $this->actingAs(panelUser(Role::Admin));
        $this->patch('/painel/organization', ['name' => 'X', 'primary' => 'vermelho'])->assertSessionHasErrors('primary');

        $this->actingAs(panelUser(Role::Organizer));
        $this->get('/painel/organization')->assertForbidden();
        $this->patch('/painel/organization', ['name' => 'Invasor', 'primary' => '#000000'])->assertSessionHas('error');

        expect(CurrentOrganization::get()->fresh()->name)->toBe('Liga Local');
    });
});

describe('search', function () {
    it('finds published competitions and teams by name, never drafts, athletes or other organizations', function () {
        ['game' => $game, 'home' => $home, 'competition' => $competition] = publishedGame();
        Team::query()->whereKey($home->team_id)->update(['name' => 'Leões da Ilha', 'city' => 'Ilhabela']);
        $competition->update(['name' => 'Copa Ilhabela']);
        $draft = Competition::factory()->create(['name' => 'Copa Ilhabela Secreta']);
        Season::factory()->create(['competition_id' => $draft->id]);
        Team::factory()->create(['name' => 'Leões Rascunho']);
        Person::query()->whereIn('id', RosterEntry::query()->where('entry_id', $home->id)->pluck('person_id'))->update(['name' => 'Leonardo Atleta']);

        $this->get('/busca?q=ilha')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Portal/Search')->where('query', 'ilha')->where('seo.noindex', true)
            ->has('competitions', 1)->where('competitions.0.name', 'Copa Ilhabela')
            ->has('teams', 1)->where('teams.0.name', 'Leões da Ilha'));

        $this->get('/busca?q=le%C3%B5es')->assertInertia(fn ($page) => $page->has('teams', 1)->where('teams.0.name', 'Leões da Ilha'));
        $this->get('/busca?q=Leonardo')->assertInertia(fn ($page) => $page->has('teams', 0)->has('competitions', 0));
    });

    it('needs two letters, escapes wildcards and trims the term', function () {
        publishedGame();

        $this->get('/busca')->assertOk()->assertInertia(fn ($page) => $page->where('query', '')->has('teams', 0)->has('competitions', 0));
        $this->get('/busca?q=a')->assertInertia(fn ($page) => $page->has('teams', 0)->has('competitions', 0));
        $this->get('/busca?q=%25%25')->assertInertia(fn ($page) => $page->has('teams', 0)->has('competitions', 0));
        $this->get('/busca?q='.str_repeat('x', 200))->assertInertia(fn ($page) => $page->where('query', str_repeat('x', 60)));
    });

    it('is kept out of the robots file', function () {
        $this->get('/robots.txt')->assertSee('Disallow: /busca');
    });
});

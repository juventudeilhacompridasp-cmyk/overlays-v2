<?php

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function dashboardGame(Season $season, GameStatus $status = GameStatus::Finished): Game
{
    $division = Division::factory()->create(['season_id' => $season->id]);
    $stage = Stage::factory()->for($division)->create();

    return Game::factory()->create(['stage_id' => $stage->id, 'status' => $status, 'finished_at' => now(), 'scheduled_at' => now()->addDay()]);
}

function dashboardSeason(string $competition, string $season = 'Edição 2026'): Season
{
    return Season::factory()->create(['competition_id' => Competition::factory()->create(['name' => $competition])->id, 'name' => $season]);
}

it('filters the games by competition and by season, and corrects the competition when the season belongs to another', function () {
    $seasonOne = dashboardSeason('Copa Um');
    $seasonTwo = dashboardSeason('Copa Dois');
    dashboardGame($seasonOne);
    dashboardGame($seasonOne);
    dashboardGame($seasonTwo);
    $this->actingAs(panelUser());

    $thisMonth = fn (int $expected) => fn ($value) => collect($value)->last()['value'] === $expected;

    $this->get('/painel')->assertInertia(fn ($page) => $page->where('games_by_month', $thisMonth(3)));
    $this->get('/painel?competition='.$seasonOne->competition_id)->assertInertia(fn ($page) => $page
        ->where('games_by_month', $thisMonth(2))->where('filters.competition', $seasonOne->competition_id)->where('filters.season', null));
    $this->get('/painel?competition='.$seasonTwo->competition_id)->assertInertia(fn ($page) => $page->where('games_by_month', $thisMonth(1)));

    // Edição de outra competição prevalece e corrige o filtro da competição.
    $this->get('/painel?competition='.$seasonOne->competition_id.'&season='.$seasonTwo->id)->assertInertia(fn ($page) => $page
        ->where('games_by_month', $thisMonth(1))->where('filters.competition', $seasonTwo->competition_id)->where('filters.season', $seasonTwo->id));
});

it('filters the status breakdown and the upcoming games too', function () {
    $seasonOne = dashboardSeason('Copa Um');
    $seasonTwo = dashboardSeason('Copa Dois');
    dashboardGame($seasonOne, GameStatus::Scheduled);
    dashboardGame($seasonTwo, GameStatus::Scheduled);
    dashboardGame($seasonTwo, GameStatus::Scheduled);
    $this->actingAs(panelUser());

    $this->get('/painel')->assertInertia(fn ($page) => $page->where('status_breakdown.scheduled', 3)->has('upcoming_games', 3));
    $this->get('/painel?season='.$seasonTwo->id)->assertInertia(fn ($page) => $page->where('status_breakdown.scheduled', 2)->has('upcoming_games', 2));
});

it('ignores unknown filters and limits the months to the allowed windows', function () {
    $this->actingAs(panelUser());

    $this->get('/painel?competition=nao-existe&season=nao-existe&months=99')->assertOk()->assertInertia(fn ($page) => $page
        ->where('filters.competition', null)->where('filters.season', null)->where('filters.months', 6)->has('games_by_month', 6));

    $this->get('/painel?months=12')->assertInertia(fn ($page) => $page->where('filters.months', 12)->has('games_by_month', 12));
    $this->get('/painel?months=3')->assertInertia(fn ($page) => $page->has('games_by_month', 3));
});

it('lists the competitions and the seasons of the chosen competition', function () {
    $one = dashboardSeason('Copa Um', 'Edição 2026');
    dashboardSeason('Copa Dois', 'Edição X');
    $this->actingAs(panelUser());

    $this->get('/painel')->assertInertia(fn ($page) => $page->has('competitions', 2)->has('seasons', 2));
    $this->get('/painel?competition='.$one->competition_id)->assertInertia(fn ($page) => $page->has('seasons', 1)->where('seasons.0.name', 'Copa Um · Edição 2026'));
});

it('exports the same numbers as CSV, with the BOM, semicolons and formulas neutralized', function () {
    $season = dashboardSeason('Copa Um');
    dashboardGame($season);
    $upcoming = dashboardGame($season, GameStatus::Scheduled);
    $upcoming->division->update(['name' => '=SOMA(A1)']);
    $this->actingAs(panelUser());

    $response = $this->get('/painel/resumo.csv?competition='.$season->competition_id);
    $csv = $response->streamedContent();

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    // O fputcsv põe entre aspas o campo com espaço ("Jogos encerrados").
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($csv)->toContain('Indicador;Valor')
        ->and($csv)->toContain('Mês;"Jogos encerrados"')
        ->and($csv)->toContain('Encerrados;1')
        ->and($csv)->toContain("'=SOMA(A1)")
        ->and($csv)->not->toContain(';=SOMA(A1)');
});

it('is for any member and never for guests', function () {
    $this->get('/painel/resumo.csv')->assertRedirect();

    $this->actingAs(panelUser(Role::Viewer))->get('/painel/resumo.csv')->assertOk();
});

it('compares the competitions by finished games, ignoring the competition and season filters', function () {
    $one = dashboardSeason('Copa Um');
    $two = dashboardSeason('Copa Dois');
    dashboardGame($one);
    dashboardGame($one);
    dashboardGame($one, GameStatus::Scheduled);
    dashboardGame($two);
    $this->actingAs(panelUser());

    $expected = fn ($rows) => collect($rows)->map(fn ($row) => [$row['name'], $row['finished_window'], $row['finished_total'], $row['open'], $row['seasons']])->all() === [['Copa Um', 2, 2, 1, 1], ['Copa Dois', 1, 1, 0, 1]];

    $this->get('/painel')->assertInertia(fn ($page) => $page->where('comparison', $expected));
    $this->get('/painel?competition='.$two->competition_id)->assertInertia(fn ($page) => $page->where('comparison', $expected));
    $this->get('/painel?months=12')->assertInertia(fn ($page) => $page->where('comparison.0.per_month', 0.2));
});

it('exports the comparison too', function () {
    dashboardGame(dashboardSeason('Copa Um'));
    $this->actingAs(panelUser());

    expect($this->get('/painel/resumo.csv')->streamedContent())->toContain('Comparativo: competição;Edições');
});

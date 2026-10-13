<?php

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** Mata-mata publicado com N equipes (cabeças de chave 1..N em `entries`). */
function bracketStage(int $teams, int $legs = 1): array
{
    $data = fixtureDivision($teams, StageType::Knockout);
    app(GenerateStageFixtures::class)->handle($data['stage'], legs: $legs);
    publishSeason(Game::query()->where('stage_id', $data['stage']->id)->firstOrFail());

    return $data;
}

function bracketGames(string $stageId, int $round)
{
    return Game::query()->where('stage_id', $stageId)->where('round', $round)->orderBy('number')->orderBy('leg')->get();
}

function bracketPage(object $stage)
{
    $season = $stage->division->season()->first();

    return test()->get(route('portal.season', [$season->competition->slug, $season->getKey()]));
}

describe('the knockout bracket', function () {
    it('shows the rounds and ties, with the open slots still empty', function () {
        ['stage' => $stage, 'entries' => $entries] = bracketStage(4);

        bracketPage($stage)->assertOk()->assertInertia(function ($page) use ($stage, $entries) {
            $bracket = $page->toArray()['props']['extras']['brackets'][$stage->id];

            expect(array_column($bracket['rounds'], 'label'))->toBe(['Semifinal', 'Final'])
                ->and($bracket['rounds'][0]['ties'])->toHaveCount(2)
                ->and($bracket['rounds'][0]['ties'][0]['a']['team']['name'])->toBe($entries[0]->team->name)
                ->and($bracket['rounds'][0]['ties'][0]['b']['team']['name'])->toBe($entries[3]->team->name)
                ->and($bracket['rounds'][1]['ties'][0]['a']['team'])->toBeNull()
                ->and($bracket['champion'])->toBeNull();
        });
    });

    it('advances winners, marks them and names the champion after the final', function () {
        ['stage' => $stage, 'entries' => $entries] = bracketStage(4);
        $semis = bracketGames($stage->id, 1);
        app(RecordQuickResult::class)->handle($semis[0], 2, 0);
        app(RecordQuickResult::class)->handle($semis[1], 1, 0);

        bracketPage($stage)->assertInertia(function ($page) use ($stage, $entries) {
            $bracket = $page->toArray()['props']['extras']['brackets'][$stage->id];
            $semi = $bracket['rounds'][0]['ties'][0];

            expect($semi['a'])->toMatchArray(['score' => 2, 'winner' => true])
                ->and($semi['b'])->toMatchArray(['score' => 0, 'winner' => false])
                ->and($semi['decided'])->toBeTrue()
                ->and($bracket['rounds'][1]['ties'][0]['a']['team']['name'])->toBe($entries[0]->team->name)
                ->and($bracket['champion'])->toBeNull();
        });

        app(RecordQuickResult::class)->handle(bracketGames($stage->id, 2)->first()->fresh(), 3, 1);

        bracketPage($stage)->assertInertia(fn ($page) => $page->where("extras.brackets.{$stage->id}.champion.name", $entries[0]->team->name));
    });

    it('decides a drawn single game by the shootout', function () {
        ['stage' => $stage] = bracketStage(2);
        $final = bracketGames($stage->id, 1)->first();
        app(RecordQuickResult::class)->handle($final, 1, 1, null, 4, 3);

        bracketPage($stage)->assertInertia(function ($page) use ($stage) {
            $tie = $page->toArray()['props']['extras']['brackets'][$stage->id]['rounds'][0]['ties'][0];

            expect($tie['a'])->toMatchArray(['score' => 1, 'winner' => true])
                ->and($tie['b'])->toMatchArray(['score' => 1, 'winner' => false])
                ->and($tie['penalties'])->toBe(['a' => 4, 'b' => 3]);
        });
    });

    it('sums a two-legged tie for each team, whoever was at home', function () {
        ['stage' => $stage, 'entries' => $entries] = bracketStage(2, legs: 2);
        [$first, $second] = bracketGames($stage->id, 1)->all();

        // Ida: o melhor cabeça de chave visita e perde por 1 x 0; na volta, em casa, vence por 2 x 0.
        app(RecordQuickResult::class)->handle($first, 1, 0);
        app(RecordQuickResult::class)->handle($second, 2, 0);

        bracketPage($stage)->assertInertia(function ($page) use ($stage, $entries) {
            $tie = $page->toArray()['props']['extras']['brackets'][$stage->id]['rounds'][0]['ties'][0];

            expect($tie['a']['team']['name'])->toBe($entries[0]->team->name)
                ->and($tie['a'])->toMatchArray(['score' => 2, 'winner' => true])
                ->and($tie['b'])->toMatchArray(['score' => 1, 'winner' => false])
                ->and($tie['legs'])->toHaveCount(2)
                ->and($tie['legs'][0]['leg'])->toBe(1);
        });
    });

    it('counts a game in progress and flags the tie as live, with no winner yet', function () {
        ['stage' => $stage] = bracketStage(2);
        $game = bracketGames($stage->id, 1)->first();
        $game->forceFill(['status' => GameStatus::InProgress, 'home_score' => 1, 'away_score' => 0])->save();

        bracketPage($stage)->assertInertia(function ($page) use ($stage) {
            $tie = $page->toArray()['props']['extras']['brackets'][$stage->id]['rounds'][0]['ties'][0];

            expect($tie['live'])->toBeTrue()
                ->and($tie['decided'])->toBeFalse()
                ->and($tie['a'])->toMatchArray(['score' => 1, 'winner' => false]);
        });
    });

    it('does not exist for league stages and can be switched off', function () {
        ['stage' => $league] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($league);
        publishSeason(Game::query()->where('stage_id', $league->id)->firstOrFail());

        bracketPage($league)->assertInertia(fn ($page) => $page->where('extras.brackets', []));

        ['stage' => $stage] = bracketStage(2);
        CurrentOrganization::get()->update(['settings' => ['portal' => ['season_bracket' => false]]]);

        bracketPage($stage)->assertInertia(fn ($page) => $page->where('extras.brackets', null));
    });
});

describe('the table rules explained', function () {
    it('lists the points and the tiebreakers in order, for football', function () {
        ['stage' => $stage] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($stage);
        publishSeason(Game::query()->where('stage_id', $stage->id)->firstOrFail());

        bracketPage($stage)->assertInertia(function ($page) use ($stage) {
            $rules = $page->toArray()['props']['extras']['rules'][$stage->division_id];

            expect($rules['points'])->toBe([['label' => 'Vitória', 'value' => 3], ['label' => 'Empate', 'value' => 1], ['label' => 'Derrota', 'value' => 0]])
                ->and($rules['tiebreakers'][0])->toBe('Mais vitórias')
                ->and($rules['tiebreakers'])->toContain('Maior saldo de gols', 'Confronto direto (só entre duas equipes)');
        });
    });

    it('follows the sport: no draw in volleyball, narrow results score differently', function () {
        ['stage' => $stage] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($stage);
        publishSeason(Game::query()->where('stage_id', $stage->id)->firstOrFail());
        $stage->division->season->competition->update(['sport' => 'volleyball']);

        bracketPage($stage)->assertInertia(function ($page) use ($stage) {
            $rules = $page->toArray()['props']['extras']['rules'][$stage->division_id];
            $labels = array_column($rules['points'], 'label');

            expect($labels)->not->toContain('Empate')
                ->and($labels)->toContain('Vitória apertada (ex.: 3 x 2 em sets)')
                ->and($rules['tiebreakers'])->toContain('Maior razão de sets (ganhos ÷ perdidos)');
        });
    });

    it('can be switched off', function () {
        ['stage' => $stage] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($stage);
        publishSeason(Game::query()->where('stage_id', $stage->id)->firstOrFail());
        CurrentOrganization::get()->update(['settings' => ['portal' => ['season_rules' => false]]]);

        bracketPage($stage)->assertInertia(fn ($page) => $page->where('extras.rules', null));
    });
});

it('keeps a draft season out of the public portal', function () {
    ['stage' => $stage] = fixtureDivision(2);
    $season = $stage->division->season()->first();

    $this->get(route('portal.season', [$season->competition->slug, $season->getKey()]))->assertNotFound();
});

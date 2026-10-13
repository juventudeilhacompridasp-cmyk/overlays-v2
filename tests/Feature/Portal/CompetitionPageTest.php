<?php

use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** Final de mata-mata com 2 equipes, já decidida (a equipe 1 vence por 3 x 1). */
function decidedFinalForCompetitionPage(): array
{
    $data = fixtureDivision(2, StageType::Knockout);
    app(GenerateStageFixtures::class)->handle($data['stage']);
    $game = Game::query()->where('stage_id', $data['stage']->id)->firstOrFail();
    publishSeason($game);
    app(RecordQuickResult::class)->handle($game->fresh(), 3, 1);

    return $data + ['season' => $data['stage']->division->season()->first()];
}

function competitionPageUrl(Season $season): string
{
    return route('portal.competition', $season->competition->slug);
}

describe('the competition page', function () {
    it('lists published seasons with champion, runner-up, titles and records', function () {
        ['entries' => $entries, 'season' => $season] = decidedFinalForCompetitionPage();
        Season::factory()->create(['competition_id' => $season->competition_id, 'name' => 'Rascunho', 'year' => 2099, 'status' => SeasonStatus::Draft]);

        $this->get(competitionPageUrl($season))->assertOk()->assertInertia(function ($page) use ($entries, $season) {
            $props = $page->toArray()['props'];
            $podium = $props['seasons'][0]['divisions'][0];

            expect($props['seasons'])->toHaveCount(1)
                ->and($props['seasons'][0]['id'])->toBe($season->id)
                ->and($podium['champion']['name'])->toBe($entries[0]->team->name)
                ->and($podium['runner_up']['name'])->toBe($entries[1]->team->name)
                ->and($props['titles'][0]['titles'])->toBe(1)
                ->and($props['records']['games'])->toBe(1)
                ->and($props['records']['total'])->toBe(4)
                ->and($props['records']['unit'])->toBe('gols')
                ->and($props['records']['biggest_wins'][0]['season'])->toBe($season->name);
        });
    });

    it('does not name a champion before the final is decided', function () {
        $data = fixtureDivision(2, StageType::Knockout);
        app(GenerateStageFixtures::class)->handle($data['stage']);
        publishSeason(Game::query()->where('stage_id', $data['stage']->id)->firstOrFail());
        $season = $data['stage']->division->season()->first();

        $this->get(competitionPageUrl($season))->assertInertia(fn ($page) => $page
            ->where('seasons.0.divisions.0.champion', null)
            ->has('titles', 0)
            ->where('records.games', 0));
    });

    it('hides champions and records when the switches are off', function () {
        ['season' => $season] = decidedFinalForCompetitionPage();
        CurrentOrganization::get()->forceFill(['settings' => ['portal' => ['competition_champions' => false, 'competition_records' => false]]])->save();

        $this->get(competitionPageUrl($season))->assertInertia(fn ($page) => $page
            ->has('seasons.0.divisions', 0)
            ->has('titles', 0)
            ->where('records', null));
    });

    it('is a 404 without published seasons and never exposes a draft', function () {
        $empty = Competition::factory()->create();
        Season::factory()->create(['competition_id' => $empty->id, 'status' => SeasonStatus::Draft]);

        $this->get(route('portal.competition', $empty->slug))->assertNotFound();
    });
});

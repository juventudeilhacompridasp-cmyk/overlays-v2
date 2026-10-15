<?php

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\MesarioSettings;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function parametersPayload(array $override = []): array
{
    return $override + ['disabled' => [], 'mesario' => ['days_ahead' => 2, 'finished_hours' => 6, 'limit' => 40]];
}

describe('features the organization turns off by itself', function () {
    it('only reduces the package: the feature disappears for the organization and comes back when turned on again', function () {
        $organization = CurrentOrganization::get();
        expect($organization->hasFeature(Feature::Arts))->toBeTrue();

        $this->actingAs(panelUser(Role::Admin))->patch(route('panel.parameters.update'), parametersPayload(['disabled' => ['arts', 'push']]))->assertSessionHas('success');

        $organization->refresh();
        expect($organization->hasFeature(Feature::Arts))->toBeFalse()
            ->and($organization->hasFeature(Feature::Push))->toBeFalse()
            ->and($organization->hasFeature(Feature::Embeds))->toBeTrue()
            ->and($organization->baseFeatureKeys())->toContain('arts');

        $this->get(route('panel.arts.index'))->assertForbidden();

        $this->patch(route('panel.parameters.update'), parametersPayload())->assertSessionHas('success');
        expect($organization->fresh()->hasFeature(Feature::Arts))->toBeTrue();
    });

    it('refuses to turn off what is not on the allowed list (overlays, custom domain) or invented keys', function () {
        $admin = panelUser(Role::Admin);

        foreach (['overlays', 'custom_domain', 'white_label', 'nope'] as $key) {
            $this->actingAs($admin)->patch(route('panel.parameters.update'), parametersPayload(['disabled' => [$key]]))->assertSessionHasErrors('disabled.0');
        }

        expect(CurrentOrganization::get()->fresh()->hasFeature(Feature::Overlays))->toBeTrue();
    });

    it('never turns on what the package does not have', function () {
        CurrentOrganization::get()->update(['features' => ['reports'], 'settings' => ['features_disabled' => ['reports']]]);

        $organization = CurrentOrganization::get()->fresh();

        expect($organization->featureKeys())->not->toContain('reports')->and($organization->featureKeys())->not->toContain('arts');
    });

    it('lists only the features the package gives, with their current state', function () {
        CurrentOrganization::get()->update(['features' => ['reports', 'arts'], 'settings' => ['features_disabled' => ['arts']]]);

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.parameters.edit'))->assertInertia(fn ($page) => $page
            ->component('Panel/Parameters/Edit')
            ->where('features', fn ($list) => collect($list)->pluck('key')->all() === ['reports', 'arts'] && collect($list)->firstWhere('key', 'arts')['off'] === true && collect($list)->firstWhere('key', 'reports')['off'] === false));
    });
});

describe('mesário windows', function () {
    it('keeps the original windows by default and clamps saved values', function () {
        expect(MesarioSettings::all())->toBe(['days_ahead' => 2, 'finished_hours' => 6, 'limit' => 40]);

        CurrentOrganization::get()->update(['settings' => ['mesario' => ['days_ahead' => 99, 'finished_hours' => 0, 'limit' => 'x']]]);

        expect(MesarioSettings::all())->toBe(['days_ahead' => 7, 'finished_hours' => 1, 'limit' => 40]);
    });

    it('changes which games the mesário sees', function () {
        ['stage' => $stage] = fixtureDivision(4);
        app(App\Modules\Matches\Actions\GenerateStageFixtures::class)->handle($stage);
        $games = App\Modules\Matches\Models\Game::query()->where('stage_id', $stage->id)->orderBy('id')->get();
        $games[0]->update(['scheduled_at' => now()->addDays(5)]);
        $games[1]->update(['scheduled_at' => now()->addHours(2), 'status' => GameStatus::Scheduled]);

        $admin = panelUser(Role::Admin);
        $sees = fn (string $id, ?int $atMost = null) => $this->actingAs($admin)->get('/painel/mesario')->assertInertia(fn ($page) => $page->where('games', fn ($list) => $atMost !== null
            ? count($list) <= $atMost
            : collect($list)->pluck('id')->contains($id)));

        // Fora da janela de 2 dias: não aparece.
        $this->actingAs($admin)->get('/painel/mesario')->assertInertia(fn ($page) => $page->where('games', fn ($list) => ! collect($list)->pluck('id')->contains($games[0]->id)));

        $this->patch(route('panel.parameters.update'), parametersPayload(['mesario' => ['days_ahead' => 7, 'finished_hours' => 6, 'limit' => 40]]))->assertSessionHas('success');
        $sees($games[0]->id);

        $this->patch(route('panel.parameters.update'), parametersPayload(['mesario' => ['days_ahead' => 7, 'finished_hours' => 6, 'limit' => 10]]))->assertSessionHas('success');
        $sees('', 10);
    });

    it('validates the ranges', function () {
        $this->actingAs(panelUser(Role::Admin))->patch(route('panel.parameters.update'), parametersPayload(['mesario' => ['days_ahead' => 9, 'finished_hours' => 0, 'limit' => 500]]))
            ->assertSessionHasErrors(['mesario.days_ahead', 'mesario.finished_hours', 'mesario.limit']);
    });
});

it('is for administrators only', function () {
    $this->actingAs(panelUser(Role::Organizer))->get(route('panel.parameters.edit'))->assertForbidden();
    $this->actingAs(panelUser(Role::Organizer))->patch(route('panel.parameters.update'), parametersPayload(['disabled' => ['arts']]))->assertRedirect();

    expect(CurrentOrganization::get()->fresh()->hasFeature(Feature::Arts))->toBeTrue();
});

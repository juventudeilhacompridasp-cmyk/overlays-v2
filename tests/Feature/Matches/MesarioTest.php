<?php

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Collection;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function mesarioGames(int $teams = 4): Collection
{
    ['stage' => $stage] = fixtureDivision($teams);
    app(GenerateStageFixtures::class)->handle($stage);

    return Game::query()->where('stage_id', $stage->id)->orderBy('id')->get();
}

it('lists live, today and upcoming games, live first, and leaves old or far ones out', function () {
    $games = mesarioGames();
    [$live, $today, $soon, $far, $done] = [$games[0], $games[1], $games[2], $games[3], $games[4]];

    $live->update(['status' => GameStatus::InProgress, 'scheduled_at' => now()->subHour()]);
    $today->update(['scheduled_at' => now()->addHours(2)]);
    $soon->update(['scheduled_at' => now()->addDay()]);
    $far->update(['scheduled_at' => now()->addDays(10)]);
    app(RecordQuickResult::class)->handle($done, 1, 0);

    $this->actingAs(panelUser(Role::Operator));

    $this->get('/painel/mesario')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Panel/Mesario/Index')
        ->where('games.0.id', $live->id)
        ->where('games.0.live', true)
        ->where('games.1.id', $today->id)
        ->where('games.2.id', $soon->id)
        ->where('games', fn ($list) => ! collect($list)->pluck('id')->contains($far->id) && collect($list)->pluck('id')->contains($done->id))
        ->where('games.0.quick_result', true));
});

it('offers the quick result only where the score is not made of sets', function () {
    $games = mesarioGames(2);
    $games[0]->update(['scheduled_at' => now()->addHour()]);
    $games[0]->division->season->competition->update(['sport' => Sport::Volleyball]);
    $this->actingAs(panelUser(Role::Operator));

    $this->get('/painel/mesario')->assertInertia(fn ($page) => $page->where('games.0.quick_result', false));
});

it('is readable by any member, and the app shell files are in place', function () {
    $this->actingAs(panelUser(Role::Viewer));
    $this->get('/painel/mesario')->assertOk();

    $manifest = json_decode((string) file_get_contents(public_path('mesario.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['start_url'])->toBe('/painel/mesario')
        ->and($manifest['display'])->toBe('standalone')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512');

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }

    expect(public_path('sw.js'))->toBeFile()->and(public_path('offline.html'))->toBeFile();
});

it('never caches panel pages or API responses in the service worker', function () {
    $worker = (string) file_get_contents(public_path('sw.js'));

    // só os arquivos versionados e os ícones são guardados; páginas vão sempre à rede
    expect($worker)->toContain("'/build/'")
        ->and($worker)->not->toContain('/painel')
        ->and($worker)->not->toContain('/api')
        ->and($worker)->toContain("request.method !== 'GET'");
});

it('links the manifest only in panel pages', function () {
    $this->actingAs(panelUser(Role::Operator));

    $this->get('/painel/mesario')->assertSee('rel="manifest"', false);
    $this->get('/')->assertDontSee('rel="manifest"', false);
});

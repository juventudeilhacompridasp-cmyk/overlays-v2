<?php

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Broadcast\Support\OverlayStatsSettings;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function statsChannel(?Game $game = null, array $screens = [], array $lineup = []): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => $game?->id, 'layers' => ['screens' => $screens], 'lineup_settings' => $lineup]);
}

it('limits every statistic and keeps the free position pairwise', function () {
    $n = OverlayStatsSettings::normalize([
        'home' => ['shots' => 5000, 'corners' => -3, 'inventado' => 9], 'away' => 'x', 'possession' => 300, 'view' => 'x', 'metrics' => ['shots', 'shots', 'xx', 'possession', 'corners', 'fouls', 'offsides', 'saves', 'shots_on_target'],
        'style' => 'x', 'animation' => 'x', 'duration' => 999, 'scale' => 1, 'text_scale' => 999, 'x' => 30, 'note' => "  linha\ndois  ",
    ]);

    expect($n['home'])->toMatchArray(['shots' => 999, 'corners' => 0])->and($n['home'])->not->toHaveKey('inventado')
        ->and($n['away'])->toBe(array_fill_keys(OverlayStatsSettings::NUMBERS, 0))
        ->and($n)->toMatchArray(['possession' => 100, 'view' => 'comparative', 'style' => 'broadcast', 'animation' => 'rise', 'duration' => 120, 'scale' => 50, 'text_scale' => 200, 'x' => null, 'y' => null])
        ->and($n['metrics'])->toHaveCount(6)->and($n['metrics'])->not->toContain('xx')
        ->and($n['note'])->not->toContain("\n");
});

it('stays out of the state until the screen is on, then sends the comparison rows', function () {
    ['game' => $game] = sheetGame();
    $channel = statsChannel($game, []);
    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);

    expect(OverlayState::for($channel)['stats'])->toBeNull();

    $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => ['home' => ['shots' => 7, 'corners' => 3], 'away' => ['shots' => 2], 'possession' => 62, 'metrics' => ['possession', 'shots', 'corners']], 'screens_live' => ['stats' => true]])->assertSessionHasNoErrors();

    $stats = OverlayState::for($channel->fresh())['stats'];
    expect($stats['view'])->toBe('comparative')
        ->and($stats['rows'])->toBe([
            ['key' => 'possession', 'label' => 'Posse de bola', 'home' => 62, 'away' => 38],
            ['key' => 'shots', 'label' => 'Finalizações', 'home' => 7, 'away' => 2],
            ['key' => 'corners', 'label' => 'Escanteios', 'home' => 3, 'away' => 0],
        ])->and($stats['events'])->toBeNull()->and($stats['player'])->toBeNull();
    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['stats'] !== null);

    // outro lançamento mescla indicador a indicador
    $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => ['home' => ['fouls' => 4]]])->assertSessionHasNoErrors();
    expect($channel->fresh()->stats_settings['home'])->toMatchArray(['shots' => 7, 'corners' => 3, 'fouls' => 4]);
});

it('marks when the screen leaves by itself, or keeps it until hidden when the duration is zero', function () {
    ['game' => $game] = sheetGame();
    $channel = statsChannel($game);
    $this->actingAs(panelUser(Role::Operator));

    $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => ['duration' => 20], 'screens_live' => ['stats' => true]])->assertSessionHasNoErrors();
    $expires = OverlayState::for($channel->fresh())['stats']['expires_ms'];
    expect($expires)->toBeGreaterThan((int) (microtime(true) * 1000) + 15_000)->and($expires)->toBeLessThan((int) (microtime(true) * 1000) + 25_000);

    // mudar os números com a tela no ar não mexe no prazo
    $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => ['home' => ['shots' => 1], 'expires_ms' => 1]])->assertSessionHasNoErrors();
    expect(OverlayState::for($channel->fresh())['stats']['expires_ms'])->toBe($expires);

    $this->patch("/painel/broadcast/{$channel->id}", ['screens_live' => ['stats' => false]]);
    expect($channel->fresh()->stats_settings['expires_ms'])->toBeNull();

    $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => ['duration' => 0], 'screens_live' => ['stats' => true]]);
    expect(OverlayState::for($channel->fresh())['stats']['expires_ms'])->toBeNull();
});

it('zeroes the numbers and the athlete when the game changes, and keeps the look', function () {
    ['game' => $game] = sheetGame();
    $channel = statsChannel($game);
    $this->actingAs(panelUser(Role::Operator));

    $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => ['home' => ['shots' => 9], 'possession' => 70, 'note' => 'Craque', 'style' => 'glass', 'animation' => 'zoom']]);
    expect($channel->fresh()->stats_settings['home']['shots'])->toBe(9);

    $this->patch("/painel/broadcast/{$channel->id}", ['game_id' => null])->assertSessionHasNoErrors();

    expect($channel->fresh()->stats_settings)->toMatchArray(['possession' => 50, 'note' => '', 'style' => 'glass', 'animation' => 'zoom', 'player' => null])
        ->and($channel->fresh()->stats_settings['home']['shots'])->toBe(0);
});

it('lists the goals, and the cards only when the portal releases them', function () {
    ['game' => $game, 'hp' => $hp, 'ap' => $ap] = sheetGame();
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Goal, $game->home_entry_id, $hp[0], null, 1, 10);
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::YellowCard, $game->away_entry_id, $ap[0], null, 1, 20);
    $channel = statsChannel($game, ['stats' => true]);
    $channel->update(['stats_settings' => ['view' => 'events']]);

    $events = OverlayState::for($channel->fresh())['stats']['events'];
    expect($events['goals'])->toHaveCount(1)->and($events['goals'][0]['side'])->toBe('home')->and($events['cards'])->toBeNull();

    CurrentOrganization::get()->update(['settings' => ['portal' => ['game_cards' => true]]]);
    $events = OverlayState::for($channel->fresh())['stats']['events'];
    expect($events['cards'])->toHaveCount(1)->and($events['cards'][0]['side'])->toBe('away')->and($events['cards'][0]['kind'])->toBe('yellow_card');
});

it('presents one athlete with goals and cards, and the photo only when that athlete is enabled', function () {
    ['game' => $game, 'hp' => $hp] = sheetGame();
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Goal, $game->home_entry_id, $hp[0], null, 1, 10);
    app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::Goal, $game->home_entry_id, $hp[0], null, 1, 30);
    $channel = statsChannel($game, ['stats' => true]);
    $channel->update(['stats_settings' => ['view' => 'player', 'player' => $hp[0], 'note' => 'Artilheiro']]);

    $player = OverlayState::for($channel->fresh())['stats']['player'];
    expect($player['goals'])->toBe(2)->and($player['note'])->toBe('Artilheiro')->and($player['cards'])->toBeNull()->and($player['photo_url'])->toBeNull()
        ->and($player)->not->toHaveKey('id')->not->toHaveKey('person_id');

    // atleta que não está na escalação do jogo não aparece
    $channel->update(['stats_settings' => ['view' => 'player', 'player' => '01JXXXXXXXXXXXXXXXXXXXXXXX']]);
    expect(OverlayState::for($channel->fresh())['stats']['player'])->toBeNull();
});

it('refuses invalid values through the panel and works through the control api', function () {
    $channel = statsChannel();
    $this->actingAs(panelUser(Role::Operator));

    foreach ([['view' => 'x'], ['possession' => 101], ['style' => 'x'], ['animation' => 'x'], ['duration' => 500], ['scale' => 10], ['metrics' => ['shots', 'corners', 'fouls', 'offsides', 'saves', 'possession', 'shots_on_target']], ['x' => 200]] as $invalid) {
        $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => $invalid])->assertSessionHasErrors('stats_settings.'.array_key_first($invalid));
    }
    $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => ['home' => ['shots' => 1000]]])->assertSessionHasErrors('stats_settings.home.shots');
    $this->patch("/painel/broadcast/{$channel->id}", ['stats_settings' => ['metrics' => ['xx']]])->assertSessionHasErrors('stats_settings.metrics.0');

    $key = OverlayChannel::newControlToken();
    $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);
    ['game' => $game] = sheetGame();
    $channel->update(['game_id' => $game->id]);

    $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['stats_settings' => ['view' => 'comparative', 'possession' => 55], 'screens_live' => ['stats' => true]])
        ->assertOk()->assertJsonPath('data.stats.rows.0.home', 55);
});

it('serves the statistics screen at its own OBS URL', function () {
    $channel = statsChannel();

    $this->get($channel->overlayUrl('estatisticas'))->assertOk()->assertInertia(fn ($page) => $page->component('Overlay/Stats')->where('state.screens.stats', false)->where('state.stats', null));
});

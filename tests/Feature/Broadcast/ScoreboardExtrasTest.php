<?php

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function extrasChannel(?Game $game = null): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => $game?->id, 'layers' => []]);
}

it('lets the operator turn the scorers under the scoreboard on and off, and tells the OBS', function () {
    $channel = extrasChannel();
    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);

    expect(OverlayState::for($channel)['appearance']['scorers'])->toBeFalse();

    $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['scorers' => true]])->assertSessionHasNoErrors();
    expect($channel->fresh()->appearance['scorers'])->toBeTrue();
    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['appearance']['scorers'] === true);

    $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['scorers' => 'talvez']])->assertSessionHasErrors('appearance.scorers');
});

it('sets the stoppage time of the game on air, validates it and clears it when the game changes', function () {
    ['game' => $game] = sheetGame();
    $channel = extrasChannel($game);
    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);

    expect(OverlayState::for($channel)['stoppage'])->toBe(0);

    $this->patch("/painel/broadcast/{$channel->id}", ['stoppage_minutes' => 4])->assertSessionHasNoErrors();
    expect(OverlayState::for($channel->fresh())['stoppage'])->toBe(4);
    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['stoppage'] === 4);

    $this->patch("/painel/broadcast/{$channel->id}", ['stoppage_minutes' => 16])->assertSessionHasErrors('stoppage_minutes');
    $this->patch("/painel/broadcast/{$channel->id}", ['stoppage_minutes' => -1])->assertSessionHasErrors('stoppage_minutes');
    expect(OverlayState::for($channel->fresh())['stoppage'])->toBe(4);

    $this->patch("/painel/broadcast/{$channel->id}", ['game_id' => null])->assertSessionHasNoErrors();
    expect(OverlayState::for($channel->fresh())['stoppage'])->toBe(0);
});

it('sets the serving side, validates it, clears it with the game and serves the all-layers page', function () {
    ['game' => $game] = sheetGame();
    $channel = extrasChannel($game);
    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);

    expect(OverlayState::for($channel)['serve'])->toBeNull();

    $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['serve' => 'away']])->assertSessionHasNoErrors();
    expect(OverlayState::for($channel->fresh())['serve'])->toBe('away');
    expect(OverlayState::for($channel->fresh())['cartela'])->not->toHaveKey('serve');

    $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['serve' => 'meio']])->assertSessionHasErrors('screen_settings.serve');
    expect(OverlayState::for($channel->fresh())['serve'])->toBe('away');

    $this->get("/overlay/{$channel->token}/todos")->assertOk()->assertInertia(fn ($page) => $page->component('Overlay/All'));

    $this->patch("/painel/broadcast/{$channel->id}", ['game_id' => null])->assertSessionHasNoErrors();
    expect(OverlayState::for($channel->fresh())['serve'])->toBeNull();
});

it('keeps the stoppage out of the cartela, away from other settings, and works through the control api', function () {
    $channel = extrasChannel();
    $channel->update(['screen_settings' => ['title' => 'Intervalo', 'subtitle' => '', 'until_ms' => null, 'stoppage' => 3]]);
    $key = OverlayChannel::newControlToken();
    $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);

    expect(OverlayState::for($channel->fresh())['cartela'])->toBe(['title' => 'Intervalo', 'subtitle' => '', 'until_ms' => null]);

    $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['screen_settings' => ['title' => 'Voltamos já']])->assertOk()->assertJsonPath('data.stoppage', 3);
    $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['stoppage_minutes' => 0])->assertOk()->assertJsonPath('data.stoppage', 0)->assertJsonPath('data.cartela.title', 'Voltamos já');
});

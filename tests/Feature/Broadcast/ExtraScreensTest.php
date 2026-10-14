<?php

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayCompare;
use App\Modules\Broadcast\Support\OverlayScreenSettings;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function screensChannel(?Game $game = null): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => $game?->id, 'layers' => []]);
}

it('serves the three extra screens to guests by token', function () {
    ['game' => $game] = sheetGame();
    $channel = screensChannel($game);

    foreach (['prejogo' => 'Overlay/Pregame', 'comparativo' => 'Overlay/Compare', 'cartela' => 'Overlay/Cartela'] as $slug => $component) {
        $this->get($channel->overlayUrl($slug))->assertOk()->assertInertia(fn ($page) => $page
            ->component($component)->where('token', $channel->token)->has('state.screens')->has('state.cartela'));
    }
});

it('starts with every extra screen hidden and no comparison data', function () {
    ['game' => $game] = sheetGame();
    $state = OverlayState::for(screensChannel($game));

    expect($state['screens'])->toBe(['pregame' => false, 'compare' => false, 'cartela' => false, 'summary' => false, 'bracket' => false, 'social' => false])
        ->and($state['compare'])->toBeNull()
        ->and($state['cartela'])->toBe(['title' => 'Começamos em breve', 'subtitle' => '', 'until_ms' => null])
        ->and($state['layers'])->toBe(['lineup' => false, 'standings' => false, 'scoreboard' => true, 'lower_third' => true]);
});

it('lets the operator show and hide the screens, ignores unknown ones and tells the OBS', function () {
    $channel = screensChannel();
    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);

    $this->patch("/painel/broadcast/{$channel->id}", ['screens_live' => ['pregame' => true, 'cartela' => true, 'inventada' => true]])->assertSessionHasNoErrors();
    $this->patch("/painel/broadcast/{$channel->id}", ['screens_live' => ['cartela' => false]])->assertSessionHasNoErrors();

    $live = $channel->fresh()->screensLive();
    expect($live)->toBe(['pregame' => true, 'compare' => false, 'cartela' => false, 'summary' => false, 'bracket' => false, 'social' => false])
        ->and($channel->fresh()->layerStates())->toBe(['lineup' => false, 'standings' => false, 'scoreboard' => true, 'lower_third' => true]);
    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['screens']['pregame'] === true);

    $this->patch("/painel/broadcast/{$channel->id}", ['screens_live' => ['compare' => 'talvez']])->assertSessionHasErrors('screens_live.compare');
    $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->where('channels.0.screens_live.pregame', true)->has('channels.0.screen_settings'));
});

it('builds the comparison from the public insights, only when the screen is on, without links or names', function () {
    ['game' => $game] = sheetGame();
    $channel = screensChannel($game);

    expect(OverlayState::for($channel)['compare'])->toBeNull();

    $channel->update(['layers' => ['screens' => ['compare' => true]]]);
    $compare = OverlayState::for($channel->fresh())['compare'];

    expect(array_keys($compare))->toBe(['standings', 'form', 'head_to_head'])
        ->and($compare['standings']['home'])->toHaveKeys(['position', 'points', 'played', 'of'])
        ->and($compare['form'])->toBe(['home' => [], 'away' => []])
        ->and($compare['head_to_head'])->toBeNull()
        ->and(json_encode($compare))->not->toContain('http');
});

it('respects the portal switches in the comparison', function () {
    ['game' => $game] = sheetGame();
    CurrentOrganization::get()->update(['settings' => ['portal' => ['game_standings' => false, 'game_form' => false, 'game_head_to_head' => false]]]);

    expect(OverlayCompare::for($game))->toBe(['standings' => null, 'form' => null, 'head_to_head' => null]);
});

describe('cartela', function () {
    it('turns minutes into the end of the countdown and lets the operator clear it', function () {
        $now = 1_800_000_000_000;

        $set = OverlayScreenSettings::apply(null, ['title' => '  Intervalo ', 'subtitle' => 'Voltamos já', 'minutes' => 15], $now);
        expect($set)->toBe(['title' => 'Intervalo', 'subtitle' => 'Voltamos já', 'until_ms' => $now + 15 * 60_000]);

        expect(OverlayScreenSettings::apply($set, ['title' => 'Outro'], $now)['until_ms'])->toBe($now + 15 * 60_000)
            ->and(OverlayScreenSettings::apply($set, ['minutes' => 0], $now)['until_ms'])->toBeNull()
            ->and(OverlayScreenSettings::apply(null, ['minutes' => 999], $now)['until_ms'])->toBe($now + 180 * 60_000)
            ->and(strlen(OverlayScreenSettings::normalize(['title' => str_repeat('a', 200)])['title']))->toBe(60);
    });

    it('is set by the operator, validated, and works through the control api', function () {
        $channel = screensChannel();
        $this->actingAs(panelUser(Role::Operator));

        $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['title' => 'Intervalo', 'subtitle' => 'Voltamos já', 'minutes' => 10], 'screens_live' => ['cartela' => true]])->assertSessionHasNoErrors();

        $saved = $channel->fresh();
        expect($saved->screen_settings['title'])->toBe('Intervalo')->and($saved->screen_settings['until_ms'])->toBeGreaterThan((int) (microtime(true) * 1000))
            ->and($saved->screensLive()['cartela'])->toBeTrue();

        $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['minutes' => 500, 'title' => str_repeat('x', 61)]])->assertSessionHasErrors(['screen_settings.minutes', 'screen_settings.title']);

        $key = OverlayChannel::newControlToken();
        $saved->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);
        $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['screens_live' => ['cartela' => false], 'screen_settings' => ['title' => 'Fim', 'minutes' => 0]])
            ->assertOk()->assertJsonPath('data.screens.cartela', false)->assertJsonPath('data.cartela.title', 'Fim')->assertJsonPath('data.cartela.until_ms', null);
    });

    it('is an operator command', function () {
        $channel = screensChannel();
        $this->actingAs(panelUser(Role::Viewer));

        $this->patch("/painel/broadcast/{$channel->id}", ['screens_live' => ['cartela' => true]])->assertSessionHas('error');
        expect($channel->fresh()->screensLive()['cartela'])->toBeFalse();
    });
});

describe('social call to action', function () {
    it('keeps up to four lines and only an https link, and shows them to the OBS', function () {
        $channel = screensChannel();
        $this->actingAs(panelUser(Role::Operator));

        $this->patch("/painel/broadcast/{$channel->id}", ['screens_live' => ['social' => true], 'screen_settings' => ['social' => [
            'items' => [['label' => 'Instagram', 'value' => ' @juventude '], ['label' => '', 'value' => 'juventude.tech']],
            'url' => 'https://juventude.tech/jogos',
        ]]])->assertSessionHasNoErrors();

        $state = OverlayState::for($channel->fresh());

        expect($state['screens']['social'])->toBeTrue()
            ->and($state['social'])->toBe(['items' => [['label' => 'Instagram', 'value' => '@juventude'], ['label' => '', 'value' => 'juventude.tech']], 'url' => 'https://juventude.tech/jogos'])
            ->and($state['cartela'])->toBe(['title' => 'Começamos em breve', 'subtitle' => '', 'until_ms' => null]);

        $this->get($channel->overlayUrl('social'))->assertOk()->assertInertia(fn ($page) => $page->component('Overlay/Social')->has('state.social'));
    });

    it('refuses more than four lines, empty text and links that are not https', function () {
        $channel = screensChannel();
        $this->actingAs(panelUser(Role::Operator));

        $five = array_fill(0, 5, ['label' => 'X', 'value' => 'y']);
        $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['social' => ['items' => $five]]])->assertSessionHasErrors('screen_settings.social.items');
        $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['social' => ['items' => [['label' => 'X', 'value' => '']]]]])->assertSessionHasErrors('screen_settings.social.items.0.value');

        foreach (['http://x.com', 'javascript:alert(1)', 'https://', 'ftp://x.com/a', 'https://x.com/a b'] as $url) {
            $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['social' => ['url' => $url]]])->assertSessionHasErrors('screen_settings.social.url');
        }

        expect($channel->fresh()->screen_settings)->toBeNull();
    });

    it('drops an invalid link that got into the settings by other means', function () {
        $channel = screensChannel();
        $channel->update(['screen_settings' => ['social' => ['items' => [['label' => 'A', 'value' => 'b']], 'url' => 'javascript:alert(1)']]]);

        expect(OverlayState::for($channel->fresh())['social'])->toBe(['items' => [['label' => 'A', 'value' => 'b']], 'url' => null]);
    });
});

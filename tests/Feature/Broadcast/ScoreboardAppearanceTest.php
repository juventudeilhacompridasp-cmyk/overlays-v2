<?php

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function appearanceChannel(): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => null, 'layers' => []]);
}

it('keeps the new scoreboard parameters inside their limits', function () {
    $normalized = OverlayAppearance::normalize([
        'text_scale' => 900, 'surface' => 1, 'accent_size' => 99, 'entrance_speed' => 10, 'period_scale' => 0, 'extra_time_scale' => 999,
        'goal_word_seconds' => 0, 'goal_team_seconds' => 99, 'radius' => 500, 'shadow' => 'enorme', 'entrance' => 'voar', 'goal_animation' => 'pular',
        'goal_text' => "  GOL\nDO\tTIME  ".str_repeat('X', 40),
    ]);

    expect($normalized['text_scale'])->toBe(200)
        ->and($normalized['surface'])->toBe(30)
        ->and($normalized['accent_size'])->toBe(8)
        ->and($normalized['entrance_speed'])->toBe(50)
        ->and($normalized['period_scale'])->toBe(60)
        ->and($normalized['extra_time_scale'])->toBe(200)
        ->and($normalized['goal_word_seconds'])->toBe(1)
        ->and($normalized['goal_team_seconds'])->toBe(6)
        ->and($normalized['radius'])->toBe(40)
        ->and($normalized['shadow'])->toBe('soft')
        ->and($normalized['entrance'])->toBe('fade')
        ->and($normalized['goal_animation'])->toBe('typewriter')
        ->and(mb_strlen($normalized['goal_text']))->toBeLessThanOrEqual(20)
        ->and($normalized['goal_text'])->not->toContain("\n")->not->toContain("\t");
});

it('only uses the free position when both coordinates exist', function () {
    expect(OverlayAppearance::normalize(['x' => 30])['x'])->toBeNull()
        ->and(OverlayAppearance::normalize(['x' => 30])['y'])->toBeNull()
        ->and(OverlayAppearance::normalize(['x' => '30', 'y' => 120]))->toMatchArray(['x' => 30, 'y' => 100])
        ->and(OverlayAppearance::normalize(['x' => 30, 'y' => 40, 'radius' => ''])['radius'])->toBeNull();
});

it('falls back to the default text for an empty celebration word', function () {
    expect(OverlayAppearance::normalize(['goal_text' => '   '])['goal_text'])->toBe('GOOOL');
});

it('lets the operator set the scoreboard parameters, tells the OBS and refuses out-of-range values', function () {
    $channel = appearanceChannel();
    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);

    $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => [
        'x' => 12, 'y' => 80, 'text_scale' => 120, 'show_badge' => false, 'radius' => 8, 'surface' => 70, 'accent_size' => 2,
        'shadow' => 'none', 'entrance' => 'zoom', 'entrance_speed' => 150, 'goal_celebration' => true, 'goal_text' => 'GOLAÇO',
        'goal_animation' => 'bounce', 'goal_word_seconds' => 3, 'goal_team_seconds' => 0, 'preset' => 'neon',
    ]])->assertSessionHasNoErrors();

    expect(OverlayState::for($channel->fresh())['appearance'])->toMatchArray([
        'x' => 12, 'y' => 80, 'text_scale' => 120, 'show_badge' => false, 'radius' => 8, 'surface' => 70, 'accent_size' => 2,
        'shadow' => 'none', 'entrance' => 'zoom', 'entrance_speed' => 150, 'goal_celebration' => true, 'goal_text' => 'GOLAÇO',
        'goal_animation' => 'bounce', 'goal_word_seconds' => 3, 'goal_team_seconds' => 0, 'preset' => 'neon',
    ]);
    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['appearance']['goal_text'] === 'GOLAÇO');

    // volta ao automático / às seis posições nomeadas
    $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['x' => null, 'y' => null, 'radius' => null]])->assertSessionHasNoErrors();
    expect(OverlayState::for($channel->fresh())['appearance'])->toMatchArray(['x' => null, 'y' => null, 'radius' => null]);

    foreach ([['text_scale' => 10], ['surface' => 5], ['accent_size' => 20], ['shadow' => 'x'], ['entrance' => 'x'], ['goal_animation' => 'x'], ['goal_word_seconds' => 9], ['goal_text' => str_repeat('a', 21)], ['x' => 101]] as $invalid) {
        $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => $invalid])->assertSessionHasErrors('appearance.'.array_key_first($invalid));
    }
});

it('works through the control api and keeps the scene intact', function () {
    $channel = appearanceChannel();
    $key = OverlayChannel::newControlToken();
    $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);

    $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['appearance' => ['preset' => 'ribbon', 'goal_celebration' => true, 'x' => 5, 'y' => 6]])
        ->assertOk()->assertJsonPath('data.appearance.preset', 'ribbon')->assertJsonPath('data.appearance.goal_celebration', true)->assertJsonPath('data.appearance.x', 5);
});

it('keeps the lineup visual parameters inside their limits and the free position pairwise', function () {
    $normalized = OverlayLineupSettings::normalize([
        'layout' => 'tunnel', 'style' => 'x', 'animation' => 'cascade', 'formation_mark' => 'number', 'formation_photo_size' => 999, 'sponsor_size' => 1,
        'x' => 30, 'surface' => 1, 'radius' => 500, 'text_scale' => 5,
    ]);

    expect($normalized)->toMatchArray([
        'layout' => 'tunnel', 'style' => 'editorial', 'animation' => 'cascade', 'formation_mark' => 'number', 'formation_photo_size' => 200, 'sponsor_size' => 60,
        'x' => null, 'y' => null, 'surface' => 40, 'radius' => 40, 'text_scale' => 50,
    ])->and(OverlayLineupSettings::normalize(['x' => 20, 'y' => 40]))->toMatchArray(['x' => 20, 'y' => 40]);
});

it('lets the operator set the lineup visual, without restarting the presentation, and refuses unknown values', function () {
    $channel = appearanceChannel();
    $channel->update(['lineup_settings' => ['presentation' => 'show', 'step' => 4, 'anchor_ms' => 123]]);
    $this->actingAs(panelUser(Role::Operator));

    $this->patch("/painel/broadcast/{$channel->id}", ['lineup_settings' => ['layout' => 'stadium', 'style' => 'champions', 'animation' => 'split', 'formation_mark' => 'number', 'formation_photo_size' => 140, 'sponsor_size' => 120, 'x' => 5, 'y' => 8, 'surface' => 80, 'radius' => 6, 'text_scale' => 110]])->assertSessionHasNoErrors();

    $settings = OverlayState::for($channel->fresh())['lineup_settings'];
    expect($settings)->toMatchArray(['layout' => 'stadium', 'style' => 'champions', 'animation' => 'split', 'formation_mark' => 'number', 'formation_photo_size' => 140, 'sponsor_size' => 120, 'x' => 5, 'y' => 8, 'surface' => 80, 'radius' => 6, 'text_scale' => 110, 'step' => 4, 'anchor_ms' => 123]);

    foreach ([['layout' => 'x'], ['style' => 'x'], ['animation' => 'x'], ['formation_mark' => 'x'], ['formation_photo_size' => 20], ['sponsor_size' => 500], ['surface' => 10], ['text_scale' => 1], ['x' => 200]] as $invalid) {
        $this->patch("/painel/broadcast/{$channel->id}", ['lineup_settings' => $invalid])->assertSessionHasErrors('lineup_settings.'.array_key_first($invalid));
    }
});

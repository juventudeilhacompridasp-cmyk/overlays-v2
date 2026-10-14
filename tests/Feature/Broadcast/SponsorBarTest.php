<?php

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlaySponsor;
use App\Modules\Broadcast\Support\OverlaySponsorSettings;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Storage::fake('s3');
});

function barChannel(): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => null, 'layers' => []]);
}

it('limits every sponsor parameter and keeps the free position pairwise', function () {
    $n = OverlaySponsorSettings::normalize([
        'format' => 'x', 'style' => 'x', 'animation' => 'x', 'animation_speed' => 1, 'scale' => 999, 'text_scale' => 1, 'x' => 40,
        'bar_seconds' => 1, 'bar_transition' => 'x', 'bar_fit' => 'x', 'bar_border' => 'x', 'bar_shadow' => 'x', 'bar_scale' => 1, 'bar_x' => 500, 'bar_y' => -5, 'bar_opacity' => 1, 'bar_radius' => 99, 'bar_background' => 'vermelho',
    ]);

    expect($n)->toMatchArray([
        'format' => 'logo', 'style' => 'boxed', 'animation' => 'fade', 'animation_speed' => 50, 'scale' => 200, 'text_scale' => 50, 'x' => null, 'y' => null,
        'bar_seconds' => 3, 'bar_transition' => 'fade', 'bar_fit' => 'cover', 'bar_border' => 'thin', 'bar_shadow' => 'none', 'bar_scale' => 50, 'bar_x' => 100, 'bar_y' => 0, 'bar_opacity' => 30, 'bar_radius' => 40, 'bar_background' => '#08090d',
    ])->and(OverlaySponsorSettings::normalize(['x' => 10, 'y' => 20]))->toMatchArray(['x' => 10, 'y' => 20]);
});

it('lets the operator set the rotation look and the bar, tells the OBS and refuses invalid values', function () {
    $channel = barChannel();
    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);

    $this->patch("/painel/broadcast/{$channel->id}", ['sponsor_settings' => [
        'format' => 'banner_name', 'style' => 'ribbon', 'animation' => 'flip', 'animation_speed' => 150, 'scale' => 120, 'x' => 70, 'y' => 8,
        'bar_seconds' => 20, 'bar_transition' => 'elastic', 'bar_fit' => 'contain', 'bar_border' => 'accent', 'bar_shadow' => 'strong', 'bar_background' => '#112233', 'bar_radius' => 12,
    ]])->assertSessionHasNoErrors();

    expect(OverlayState::for($channel->fresh())['sponsors'])->toMatchArray([
        'format' => 'banner_name', 'style' => 'ribbon', 'animation' => 'flip', 'animation_speed' => 150, 'scale' => 120, 'x' => 70, 'y' => 8,
        'bar_seconds' => 20, 'bar_transition' => 'elastic', 'bar_fit' => 'contain', 'bar_border' => 'accent', 'bar_shadow' => 'strong', 'bar_background' => '#112233', 'bar_radius' => 12,
    ]);
    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['sponsors']['format'] === 'banner_name');

    foreach ([['format' => 'x'], ['style' => 'x'], ['animation' => 'x'], ['bar_transition' => 'x'], ['bar_seconds' => 90], ['bar_background' => 'azul'], ['bar_x' => 101], ['scale' => 10]] as $invalid) {
        $this->patch("/painel/broadcast/{$channel->id}", ['sponsor_settings' => $invalid])->assertSessionHasErrors('sponsor_settings.'.array_key_first($invalid));
    }
});

it('stores, replaces and removes the wide banner of a sponsor, and deletes it with the sponsor', function () {
    $this->actingAs(panelUser(Role::Admin));
    $this->post(route('panel.broadcast.sponsors.store'), ['name' => 'Padaria', 'logo' => UploadedFile::fake()->image('logo.png', 300, 120), 'banner' => UploadedFile::fake()->image('banner.png', 1500, 200)])->assertSessionHas('success');
    $sponsor = OverlaySponsor::query()->firstOrFail();

    expect($sponsor->banner_path)->not->toBeNull()->and($sponsor->bannerUrl())->toContain('-1500.webp');
    $first = $sponsor->banner_path;
    Storage::disk('s3')->assertExists($first.'-1500.webp');

    $this->patch(route('panel.broadcast.sponsors.update', $sponsor), ['banner' => UploadedFile::fake()->image('novo.jpg', 1600, 220)])->assertSessionHas('success');
    expect($sponsor->fresh()->banner_path)->not->toBe($first);
    Storage::disk('s3')->assertMissing($first.'-1500.webp');

    $this->patch(route('panel.broadcast.sponsors.update', $sponsor), ['remove_banner' => true])->assertSessionHas('success');
    expect($sponsor->fresh()->banner_path)->toBeNull();

    $this->patch(route('panel.broadcast.sponsors.update', $sponsor), ['banner' => UploadedFile::fake()->image('b.png', 1500, 200)]);
    $path = $sponsor->fresh()->banner_path;
    $this->delete(route('panel.broadcast.sponsors.destroy', $sponsor));
    Storage::disk('s3')->assertMissing($path.'-1500.webp');

    $this->post(route('panel.broadcast.sponsors.store'), ['name' => 'PDF', 'logo' => UploadedFile::fake()->image('l.png'), 'banner' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])->assertSessionHasErrors('banner');
});

it('puts the sponsor bar on air only when the operator shows it, and sends the banners then', function () {
    $channel = barChannel();
    $this->actingAs(panelUser(Role::Organizer))->post(route('panel.broadcast.sponsors.store'), ['name' => 'Padaria', 'logo' => UploadedFile::fake()->image('logo.png', 300, 120), 'banner' => UploadedFile::fake()->image('banner.png', 1500, 200)]);

    $state = OverlayState::for($channel->fresh());
    expect($state['screens']['sponsor_bar'])->toBeFalse()->and($state['sponsors']['items'])->toBe([]);

    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);
    $this->patch("/painel/broadcast/{$channel->id}", ['screens_live' => ['sponsor_bar' => true]])->assertSessionHasNoErrors();

    $state = OverlayState::for($channel->fresh());
    expect($state['screens']['sponsor_bar'])->toBeTrue()
        ->and($state['sponsors']['items'])->toHaveCount(1)
        ->and($state['sponsors']['items'][0]['banner_url'])->toContain('-1500.webp')
        ->and($state['sponsors']['enabled'])->toBeFalse();
    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['screens']['sponsor_bar'] === true);
});

it('serves the sponsor bar at its own OBS URL and lists it among the channel screens', function () {
    $channel = barChannel();

    $this->get($channel->overlayUrl('barra-patrocinio'))->assertOk()->assertInertia(fn ($page) => $page->component('Overlay/SponsorBar')->has('state.sponsors')->where('state.screens.sponsor_bar', false));
    $this->actingAs(panelUser(Role::Viewer))->get('/painel/broadcast')->assertInertia(fn ($page) => $page->where('channels.0.overlays', fn ($overlays) => collect($overlays)->pluck('slug')->contains('barra-patrocinio')));
});

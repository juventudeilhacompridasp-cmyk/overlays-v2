<?php

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function operatorLayoutTestChannel(): OverlayChannel
{
    return OverlayChannel::query()->create([
        'name' => 'Canal '.fake()->unique()->word(),
        'token' => OverlayChannel::newToken(),
        'layers' => [],
    ]);
}

it('saves the operator layout per user and channel', function () {
    $channel = operatorLayoutTestChannel();
    $user = panelUser(Role::Operator);
    $layout = [
        ['id' => 'scoreboard', 'x' => 1, 'y' => 2, 'w' => 10, 'h' => 3],
        ['id' => 'lineup', 'x' => 0, 'y' => 6, 'w' => 24, 'h' => 10],
    ];

    $this->actingAs($user)
        ->patch(route('panel.broadcast.operator-layout'), ['channel_id' => $channel->id, 'layout' => $layout])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->operator_layouts)->toBe([$channel->id => $layout]);
});

it('rejects layouts for an unknown channel', function () {
    $user = panelUser(Role::Operator);

    $this->actingAs($user)
        ->patch(route('panel.broadcast.operator-layout'), ['channel_id' => 'canal-inexistente', 'layout' => []])
        ->assertSessionHasErrors('channel_id');

    expect($user->fresh()->operator_layouts)->toBeNull();
});

<?php

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function remoteChannel(array $attributes = []): OverlayChannel
{
    return OverlayChannel::query()->create($attributes + [
        'name' => 'Canal '.fake()->unique()->word(),
        'token' => OverlayChannel::newToken(),
        'layers' => [],
    ]);
}

describe('remote control screen', function () {
    it('is readable by any panel member and brings the same channel data as the board', function () {
        ['game' => $game] = sheetGame();
        $channel = remoteChannel(['game_id' => $game->id]);

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.broadcast.remote'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Broadcast/Remote')
                ->has('channels', 1)
                ->where('channels.0.id', $channel->id)
                ->where('channels.0.game_id', $game->id)
                ->has('channels.0.layers')
                ->has('channels.0.screens_live')
                ->has('channels.0.lineup_people', 28)
                ->has('games')
                ->has('scenes'));
    });

    it('works with no channel yet', function () {
        $this->actingAs(panelUser(Role::Operator))->get(route('panel.broadcast.remote'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('channels', 0));
    });

    it('is closed to guests and to organizations without the overlays feature', function () {
        $this->get(route('panel.broadcast.remote'))->assertRedirect('/login');

        CurrentOrganization::get()->update(['plan' => Plan::Basic, 'features' => []]);

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.broadcast.remote'))->assertForbidden();
    });

    it('sends its commands through the same route as the operator mode, only for who operates games', function () {
        ['game' => $game] = sheetGame();
        $channel = remoteChannel();

        $this->actingAs(panelUser(Role::Operator))
            ->patch(route('panel.broadcast.update', $channel), ['game_id' => $game->id, 'layers' => ['lineup' => true]])
            ->assertSessionHas('success');
        expect($channel->fresh()->game_id)->toBe($game->id)
            ->and($channel->fresh()->layerStates()['lineup'])->toBeTrue();

        $this->actingAs(panelUser(Role::Viewer))->patch(route('panel.broadcast.update', $channel), ['layers' => ['lineup' => false]])->assertRedirect();
        expect($channel->fresh()->layerStates()['lineup'])->toBeTrue();
    });
});

describe('channel payload', function () {
    it('includes the stoppage minutes so the screens can show the current value', function () {
        ['game' => $game] = sheetGame();
        $channel = remoteChannel(['game_id' => $game->id]);

        $this->actingAs(panelUser(Role::Operator))->patch(route('panel.broadcast.update', $channel), ['stoppage_minutes' => 4])->assertSessionHas('success');

        $this->actingAs(panelUser(Role::Operator))->get(route('panel.broadcast.index'))->assertInertia(fn ($page) => $page->where('channels.0.stoppage', 4));
        $this->actingAs(panelUser(Role::Operator))->get(route('panel.broadcast.remote'))->assertInertia(fn ($page) => $page->where('channels.0.stoppage', 4));
    });

    it('lists as candidates only scheduled or in-progress games with both teams', function () {
        sheetGame();

        $this->actingAs(panelUser(Role::Operator))->get(route('panel.broadcast.remote'))->assertInertia(fn ($page) => $page->has('games', 1));
    });
});

it('offers the remote control as a shortcut of the installed app', function () {
    $manifest = json_decode((string) file_get_contents(public_path('mesario.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['shortcuts'][0]['url'])->toBe('/painel/broadcast/controle');
});

<?php

use App\Modules\Broadcast\Events\OverlayLowerThird;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Support\OverlayLowerThirdSettings;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function ltChannel(?Game $game = null, array $layers = [], array $settings = []): OverlayChannel
{
    return OverlayChannel::query()->create([
        'name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => $game?->id, 'layers' => $layers,
        'lower_third_settings' => $settings === [] ? null : $settings,
    ]);
}

/** Jogo com camisas 1..14 e jogadores adultos conhecidos. */
function ltGame(): array
{
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    Person::query()->whereIn('id', $hp)->update(['birth_date' => '1990-01-01']);
    Person::query()->whereKey($hp[0])->update(['name' => 'Rafael Souza', 'nickname' => 'Rafa']);
    Team::query()->whereKey($home->team_id)->update(['primary_color' => '#00aa00']);

    return [$game, $home, $hp];
}

describe('settings', function () {
    it('has six styles, seven animations and every kind on by default', function () {
        expect(OverlayLowerThirdSettings::STYLES)->toHaveCount(6)->and(OverlayLowerThirdSettings::ANIMATIONS)->toHaveCount(7);

        $state = OverlayState::for(ltChannel());

        expect($state['lower_third_settings'])->toBe(['style' => 'arena', 'animation' => 'slide', 'seconds' => 7, 'kinds' => OverlayLowerThirdSettings::KINDS, 'score' => true, 'position' => 'left', 'x' => null, 'y' => null, 'text_scale' => 100, 'card_mode' => 'banner']);
    });

    it('normalizes whatever was saved', function () {
        expect(OverlayLowerThirdSettings::normalize(['style' => 'neon', 'animation' => 'x', 'seconds' => 99, 'kinds' => ['goal', 'inventado'], 'score' => 0]))
            ->toMatchArray(['style' => 'arena', 'animation' => 'slide', 'seconds' => 15, 'kinds' => ['goal'], 'score' => false])
            ->and(OverlayLowerThirdSettings::normalize(['seconds' => 1])['seconds'])->toBe(3);
    });

    it('positions the banner, scales the text and can show cards fullscreen', function () {
        expect(OverlayLowerThirdSettings::normalize(['x' => 30]))->toMatchArray(['x' => null, 'y' => null, 'position' => 'left', 'text_scale' => 100, 'card_mode' => 'banner'])
            ->and(OverlayLowerThirdSettings::normalize(['x' => '30', 'y' => 400, 'text_scale' => 999, 'position' => 'topo', 'card_mode' => 'x']))->toMatchArray(['x' => 30, 'y' => 100, 'text_scale' => 200, 'position' => 'left', 'card_mode' => 'banner']);

        $channel = ltChannel();
        $this->actingAs(panelUser(Role::Operator));

        $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_settings' => ['position' => 'center', 'text_scale' => 130, 'card_mode' => 'fullscreen', 'x' => 10, 'y' => 70]])->assertSessionHasNoErrors();
        expect(OverlayState::for($channel->fresh())['lower_third_settings'])->toMatchArray(['position' => 'center', 'text_scale' => 130, 'card_mode' => 'fullscreen', 'x' => 10, 'y' => 70]);

        foreach ([['position' => 'meio'], ['text_scale' => 10], ['card_mode' => 'x'], ['x' => 101]] as $invalid) {
            $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_settings' => $invalid])->assertSessionHasErrors('lower_third_settings.'.array_key_first($invalid));
        }
    });

    it('lets the operator change style, animation, time and kinds, and tells the OBS', function () {
        $channel = ltChannel();
        $this->actingAs(panelUser(Role::Operator));

        foreach (OverlayLowerThirdSettings::STYLES as $style) {
            $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_settings' => ['style' => $style, 'animation' => 'zoom', 'seconds' => 10, 'kinds' => ['goal', 'red_card'], 'score' => false]])->assertSessionHasNoErrors();
            expect($channel->fresh()->lower_third_settings['style'])->toBe($style);
        }

        expect($channel->fresh()->lower_third_settings)->toMatchArray(['style' => 'flash', 'animation' => 'zoom', 'seconds' => 10, 'kinds' => ['goal', 'red_card'], 'score' => false]);

        $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_settings' => ['style' => 'neon', 'animation' => 'x', 'seconds' => 2, 'kinds' => ['gol']]])
            ->assertSessionHasErrors(['lower_third_settings.style', 'lower_third_settings.animation', 'lower_third_settings.seconds', 'lower_third_settings.kinds.0']);
    });

    it('travels with the scenes', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $channel = ltChannel(null, [], ['style' => 'slash', 'animation' => 'bounce', 'seconds' => 9]);

        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'Gols', 'channel_id' => $channel->id])->assertSessionHas('success');
        $target = ltChannel();
        $this->patch(route('panel.broadcast.scenes.apply', ['channel' => $target, 'scene' => OverlayScene::query()->firstOrFail()]))->assertSessionHas('success');

        expect($target->fresh()->lower_third_settings)->toMatchArray(['style' => 'slash', 'animation' => 'bounce', 'seconds' => 9]);
    });
});

describe('event banners', function () {
    it('carries the player, the shirt, the team and the score after a goal', function () {
        [$game, $home, $hp] = ltGame();
        ltChannel($game);
        Event::fake([OverlayLowerThird::class]);

        app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 10);

        Event::assertDispatched(OverlayLowerThird::class, function (OverlayLowerThird $event) use ($home) {
            $banner = $event->banner;

            return $banner['kind'] === 'goal' && $banner['title'] === 'GOL!' && $banner['subtitle'] === 'Rafa'
                && $banner['player']['name'] === 'Rafa' && $banner['player']['shirt'] === 1
                && $banner['team'] === $home->team->name && $banner['color'] === '#00aa00'
                && $banner['score']['home'] === 1 && $banner['score']['away'] === 0 && $banner['detail'] === null;
        });
    });

    it('announces the second goal and the hat-trick of the same player', function () {
        [$game, $home, $hp] = ltGame();
        ltChannel($game);
        Event::fake([OverlayLowerThird::class]);
        $record = app(RecordGameEvent::class);

        foreach ([10, 20, 30] as $minute) {
            $record->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[0], minute: $minute);
        }

        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->banner['detail'] === '2º gol do jogador na partida');
        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->banner['detail'] === '3º gol do jogador na partida · HAT-TRICK' && $e->banner['score']['home'] === 3);
    });

    it('shows cards without a score and counts the second yellow', function () {
        [$game, $home, $hp] = ltGame();
        ltChannel($game);
        Event::fake([OverlayLowerThird::class]);
        $record = app(RecordGameEvent::class);

        $record->handle($game, GameEventType::YellowCard, $home->id, $hp[1], minute: 5);
        $record->handle($game->fresh(), GameEventType::YellowCard, $home->id, $hp[1], minute: 50);

        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->banner['kind'] === 'yellow_card' && $e->banner['score'] === null && $e->banner['detail'] === null);
        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->banner['detail'] === '2º cartão amarelo' && $e->banner['player']['shirt'] === 2);
    });

    it('uses the public name: minors are abbreviated and no ids or documents travel', function () {
        [$game, $home, $hp] = ltGame();
        Person::query()->whereKey($hp[0])->update(['name' => 'Pedro Santos', 'nickname' => 'Pepê', 'birth_date' => now()->subYears(12)->toDateString()]);
        ltChannel($game);
        Event::fake([OverlayLowerThird::class]);

        app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 3);

        Event::assertDispatched(OverlayLowerThird::class, function (OverlayLowerThird $event) use ($hp) {
            $json = json_encode($event->banner);

            return $event->banner['subtitle'] === 'Pedro S.' && ! str_contains($json, 'Pedro Santos') && ! str_contains($json, 'Pepê') && ! str_contains($json, $hp[0]);
        });
    });

    it('honours the kinds and the score switch of each channel', function () {
        [$game, $home, $hp] = ltGame();
        $goalsOnly = ltChannel($game, [], ['kinds' => ['goal'], 'score' => false]);
        Event::fake([OverlayLowerThird::class]);
        $record = app(RecordGameEvent::class);

        $record->handle($game, GameEventType::YellowCard, $home->id, $hp[1], minute: 5);
        Event::assertNotDispatched(OverlayLowerThird::class);

        $record->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[0], minute: 6);
        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->token === $goalsOnly->token && $e->banner['score'] === null);
    });
});

describe('manual lower thirds', function () {
    it('shows a free notice without needing a game', function () {
        $channel = ltChannel();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayLowerThird::class]);

        $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_show' => ['kind' => 'custom', 'title' => 'Narração: Zé', 'subtitle' => 'Comentários: Maria']])->assertSessionHasNoErrors();

        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->token === $channel->token && $e->banner['kind'] === 'custom' && $e->banner['title'] === 'Narração: Zé' && $e->banner['subtitle'] === 'Comentários: Maria');
        expect($channel->fresh()->lower_third_settings)->toBeNull();
    });

    it('needs a title for a free notice and the lower thirds on air', function () {
        $channel = ltChannel();
        $hidden = ltChannel(null, ['lower_third' => false]);
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayLowerThird::class]);

        $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_show' => ['kind' => 'custom']])->assertSessionHasErrors('lower_third_show.title');
        $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_show' => ['kind' => 'explodir']])->assertSessionHasErrors('lower_third_show.kind');
        $this->patch("/painel/broadcast/{$hidden->id}", ['lower_third_show' => ['kind' => 'custom', 'title' => 'Oi']])->assertSessionHasNoErrors();

        Event::assertNotDispatched(OverlayLowerThird::class);
    });

    it('presents a player of the lineup with shirt and team, and ignores people outside it', function () {
        [$game, $home, $hp] = ltGame();
        $channel = ltChannel($game);
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayLowerThird::class]);

        $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_show' => ['kind' => 'player', 'person_id' => $hp[0]]])->assertSessionHasNoErrors();
        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->banner['kind'] === 'player' && $e->banner['title'] === 'Rafa' && $e->banner['player']['shirt'] === 1 && $e->banner['team'] === $home->team->name);

        $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_show' => ['kind' => 'player', 'person_id' => Person::factory()->create()->id]])->assertSessionHasNoErrors();
        Event::assertDispatchedTimes(OverlayLowerThird::class, 1);
    });

    it('sends a sample of each kind with the game on air and nothing without it', function () {
        [$game] = ltGame();
        $channel = ltChannel($game);
        $idle = ltChannel();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayLowerThird::class]);

        foreach (OverlayLowerThirdSettings::KINDS as $kind) {
            $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_show' => ['kind' => $kind]])->assertSessionHasNoErrors();
        }
        Event::assertDispatchedTimes(OverlayLowerThird::class, 6);
        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->banner['kind'] === 'goal' && $e->banner['score'] !== null && $e->banner['minute'] === 37);

        $this->patch("/painel/broadcast/{$idle->id}", ['lower_third_show' => ['kind' => 'goal']])->assertSessionHasNoErrors();
        Event::assertDispatchedTimes(OverlayLowerThird::class, 6);
    });

    it('is an operator command, also through the control api', function () {
        $channel = ltChannel();
        $this->actingAs(panelUser(Role::Viewer));
        $this->patch("/painel/broadcast/{$channel->id}", ['lower_third_show' => ['kind' => 'custom', 'title' => 'X']])->assertSessionHas('error');

        $key = OverlayChannel::newControlToken();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);
        Event::fake([OverlayLowerThird::class]);

        $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['lower_third_show' => ['kind' => 'custom', 'title' => 'Intervalo']])->assertOk();
        Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $e) => $e->banner['title'] === 'Intervalo');
    });
});

<?php

use App\Modules\Broadcast\Events\OverlayLowerThird;
use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayFont;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Models\OverlaySponsor;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlayLineupTimeline;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function channelFor(?Game $game = null, array $layers = []): OverlayChannel
{
    return OverlayChannel::query()->create([
        'name' => 'Canal '.fake()->unique()->word(),
        'token' => OverlayChannel::newToken(),
        'game_id' => $game?->id,
        'layers' => $layers,
    ]);
}

it('builds the overlay state with the score, the clock and the logos', function () {
    ['game' => $game, 'home' => $home] = sheetGame();
    Team::query()->whereKey($home->team_id)->update(['primary_color' => '#ff0000']);
    $channel = channelFor($game);

    $state = OverlayState::for($channel);

    expect($state['channel'])->toBe($channel->name)
        ->and($state['appearance'])->toBe([
            'preset' => 'arena',
            'accent_color' => '#14b8a6',
            'background_color' => '#0f172a',
            'text_color' => '#f8fafc',
            'position' => 'top-left',
            'scale' => 1.0,
            'motion' => 'dynamic',
            'font' => 'system',
            'layout' => 'bar',
            'scorers' => false,
        ])
        ->and($state['lineup_settings'])->toBe(['presentation' => 'sequence', 'list_seconds' => 6, 'team' => 'home', 'individual_mode' => 'auto', 'individual_seconds' => 6, 'individual_scope' => 'starters', 'sponsor_banner' => false, 'step' => 0, 'anchor_ms' => 0])
        ->and($state['game']['id'])->toBe($game->id)
        ->and($state['game']['home']['color'])->toBe('#ff0000')
        ->and($state['game']['clock']['state'])->toBe('not_started')
        ->and($state['layers'])->toBe(['lineup' => false, 'standings' => false, 'scoreboard' => true, 'lower_third' => true])
        ->and($state['lineup'])->toBeNull()
        ->and($state['standings'])->toBeNull();
});

it('has no game and no layers on an idle channel', function () {
    $state = OverlayState::for(channelFor());

    expect($state['game'])->toBeNull()->and($state['lineup'])->toBeNull();
});

it('only fills the lineup when the layer is on, with shirt numbers and nicknames, never cards', function () {
    ['game' => $game, 'hp' => $hp] = sheetGame();
    Person::query()->whereKey($hp[0])->update(['nickname' => 'Rafa', 'birth_date' => now()->subYears(20)]);
    app(RecordGameEvent::class)->handle($game, GameEventType::RedCard, $game->home_entry_id, $hp[1]);

    expect(OverlayState::for(channelFor($game))['lineup'])->toBeNull();

    $lineup = OverlayState::for(channelFor($game, ['lineup' => true]))['lineup'];

    expect(OverlayState::for(channelFor($game, ['lineup' => true]))['layers'])->toBe(['lineup' => true, 'standings' => false, 'scoreboard' => true, 'lower_third' => true]);

    expect($lineup['home']['starters'])->toHaveCount(11)
        ->and($lineup['home']['bench'])->toHaveCount(3)
        ->and($lineup['home']['starters'][0])->toBe([
            'shirt' => 1, 'name' => 'Rafa', 'position' => null, 'position_label' => null, 'x' => null, 'y' => null,
            'captain' => false, 'photo_url' => null, 'photo_large_url' => null,
        ])
        // Só dados visuais indispensáveis (nada de IDs, cartões, documentos ou nascimento). Confere as chaves: os nomes sorteados
        // pela fábrica podem conter "card" (ex.: Ricardo), então buscar texto no JSON era instável.
        ->and(collect([...$lineup['home']['starters'], ...$lineup['away']['starters']])->every(fn ($player) => array_keys($player) === ['shirt', 'name', 'position', 'position_label', 'x', 'y', 'captain', 'photo_url', 'photo_large_url']))->toBeTrue()
        ->and(collect([...$lineup['home']['bench'], ...$lineup['away']['bench']])->every(fn ($player) => array_keys($player) === ['shirt', 'name', 'captain', 'photo_url', 'photo_large_url']))->toBeTrue();
});

it('only publishes a short signed photo url for athletes explicitly enabled on the channel', function () {
    ['game' => $game, 'hp' => $hp] = sheetGame();
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://private.test/{$path}");
    Person::query()->whereIn('id', [$hp[0], $hp[1]])->update(['photo_path' => 'people/test/photo']);

    $channel = channelFor($game, ['lineup' => true]);
    $channel->update(['lineup_settings' => ['presentation' => 'formation', 'list_seconds' => 8, 'photo_person_ids' => [$hp[0]]]]);
    $state = OverlayState::for($channel->fresh());

    expect($state['lineup_settings'])->toBe(['presentation' => 'formation', 'list_seconds' => 8, 'team' => 'home', 'individual_mode' => 'auto', 'individual_seconds' => 6, 'individual_scope' => 'starters', 'sponsor_banner' => false, 'step' => 0, 'anchor_ms' => 0])
        ->and($state['lineup']['home']['starters'][0]['photo_url'])->toStartWith('https://private.test/')
        ->and($state['lineup']['home']['starters'][1]['photo_url'])->toBeNull()
        ->and(json_encode($state))->not->toContain($hp[0])->not->toContain('photo_person_ids');
});

it('shows the standings of the league or group of the game and nothing for knockouts', function () {
    ['game' => $game] = sheetGame();
    $state = OverlayState::for(channelFor($game, ['standings' => true]));

    expect($state['standings']['rows'])->toHaveCount(2)
        ->and(collect($state['standings']['rows'])->every(fn (array $row) => $row['highlight']))->toBeTrue()
        ->and(array_keys($state['standings']['rows'][0]))->toBe(['position', 'team', 'logo_url', 'points', 'played', 'goal_difference', 'won', 'drawn', 'lost', 'color', 'highlight']);

    ['stage' => $stage] = fixtureDivision(4, StageType::Knockout);
    app(GenerateStageFixtures::class)->handle($stage);
    $knockoutGame = Game::query()->where('stage_id', $stage->id)->firstOrFail();

    expect(OverlayState::for(channelFor($knockoutGame, ['standings' => true]))['standings'])->toBeNull();
});

it('serves every overlay screen to guests by token and refuses unknown tokens and screens', function () {
    ['game' => $game] = sheetGame();
    $channel = channelFor($game);

    foreach (['placar' => 'Overlay/Scoreboard', 'eventos' => 'Overlay/LowerThird', 'escalacao' => 'Overlay/Lineup', 'tabela' => 'Overlay/Standings'] as $slug => $component) {
        $this->get("/overlay/{$channel->token}/{$slug}")->assertOk()->assertInertia(fn ($page) => $page
            ->component($component)
            ->where('token', $channel->token)
            ->where('state.game.id', $game->id)
            ->has('realtime.port'));
    }

    $this->getJson("/overlay/{$channel->token}/estado")->assertOk()->assertJsonPath('game.id', $game->id)->assertHeader('Cache-Control', 'no-store, private');
    $this->get("/overlay/{$channel->token}/inexistente")->assertNotFound();
    $this->get('/overlay/'.str_repeat('x', 40).'/placar')->assertNotFound();
});

it('does not serve channels of other organizations', function () {
    $channel = channelFor();
    CurrentOrganization::set(Organization::factory()->create(['slug' => 'outra']));
    config(['platform.default_organization_slug' => 'outra']);

    $this->get("/overlay/{$channel->token}/placar")->assertNotFound();
});

it('refreshes the channels that have the game on air when the score changes', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    $onAir = channelFor($game);
    $other = channelFor(Game::factory()->create());
    $idle = channelFor();

    Event::fake([OverlayUpdated::class, OverlayLowerThird::class]);
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 12);

    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->token === $onAir->token
        && $event->state['game']['home_score'] === 1
        && $event->broadcastOn()[0]->name === "overlay.{$onAir->token}"
        && $event->broadcastAs() === 'overlay.updated');
    Event::assertNotDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => in_array($event->token, [$other->token, $idle->token], true));
});

it('sends a lower third for goals, cards and substitutions of the game on air', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    Person::query()->whereKey($hp[0])->update(['nickname' => 'Rafa', 'birth_date' => '1990-01-01']);
    Team::query()->whereKey($home->team_id)->update(['primary_color' => '#00aa00']);
    $channel = channelFor($game);
    Event::fake([OverlayLowerThird::class]);

    $record = app(RecordGameEvent::class);
    $record->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 10);
    $record->handle($game->fresh(), GameEventType::YellowCard, $home->id, $hp[1], minute: 20);
    $record->handle($game->fresh(), GameEventType::RedCard, $home->id, $hp[2], minute: 30);
    $record->handle($game->fresh(), GameEventType::Substitution, $home->id, $hp[3], $hp[11], minute: 40);

    Event::assertDispatchedTimes(OverlayLowerThird::class, 4);
    Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $event) => $event->token === $channel->token
        && $event->banner['kind'] === 'goal' && $event->banner['title'] === 'GOL!' && $event->banner['subtitle'] === 'Rafa'
        && $event->banner['color'] === '#00aa00' && $event->banner['minute'] === 10 && $event->broadcastAs() === 'overlay.lowerthird');
    Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $event) => $event->banner['title'] === 'CARTÃO VERMELHO');
    Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $event) => $event->banner['title'] === 'SUBSTITUIÇÃO' && str_contains($event->banner['subtitle'], '↑') && str_contains($event->banner['subtitle'], '↓'));
});

it('does not send lower thirds when no channel has the game on air', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    channelFor(Game::factory()->create());
    Event::fake([OverlayLowerThird::class]);

    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);

    Event::assertNotDispatched(OverlayLowerThird::class);
});

it('does not send lower thirds to a channel where the operator hid them, but keeps sending to the others', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    $hidden = channelFor($game, ['lower_third' => false]);
    $visible = channelFor($game);
    Event::fake([OverlayLowerThird::class]);

    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);

    Event::assertDispatchedTimes(OverlayLowerThird::class, 1);
    Event::assertDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $event) => $event->token === $visible->token);
    Event::assertNotDispatched(OverlayLowerThird::class, fn (OverlayLowerThird $event) => $event->token === $hidden->token);
});

it('broadcasts the commands without going through the queue', function () {
    expect(new OverlayUpdated('t', []))->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and(new OverlayLowerThird('t', ['id' => 'x', 'kind' => 'goal', 'title' => 'GOL!', 'subtitle' => null, 'team' => null, 'color' => null, 'minute' => null]))->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('keeps the command and the súmula working when the broadcaster is down', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    $channel = channelFor($game);
    Event::listen(OverlayUpdated::class, fn () => throw new RuntimeException('Reverb fora do ar'));
    Event::listen(OverlayLowerThird::class, fn () => throw new RuntimeException('Reverb fora do ar'));
    $this->actingAs(panelUser(Role::Operator));

    $this->patch("/painel/broadcast/{$channel->id}", ['layers' => ['scoreboard' => false]])->assertSessionHas('success');
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0]);

    expect($channel->fresh()->layerOn('scoreboard'))->toBeFalse()
        ->and(OverlayState::for($channel->fresh())['layers']['scoreboard'])->toBeFalse()
        ->and($game->fresh()->home_score)->toBe(1);
});

it('refreshes the overlay when the clock starts', function () {
    ['game' => $game] = sheetGame();
    $channel = channelFor($game);
    Event::fake([OverlayUpdated::class]);

    app(ChangeGamePeriod::class)->handle($game, true, 1);

    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->token === $channel->token && $event->state['game']['clock']['state'] === 'running');
});

describe('panel', function () {
    it('creates, lists and deletes channels with the overlay urls', function () {
        $this->actingAs(panelUser(Role::Organizer));

        $this->post('/painel/broadcast', ['name' => 'Principal'])->assertSessionHas('success');
        $channel = OverlayChannel::query()->where('name', 'Principal')->firstOrFail();

        expect(strlen($channel->token))->toBe(40);
        expect($channel->control_token_hash)->toHaveLength(64);

        $this->get('/painel/broadcast')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Panel/Broadcast/Index')
            ->has('channels', 1)
            ->where('channels.0.overlays.0.slug', 'placar')
            ->where('channels.0.overlays.0.title', 'Placar e cronômetro')
            ->where('channels.0.overlays.0.url', url("/overlay/{$channel->token}/placar"))
            ->where('channels.0.overlays.1.slug', 'eventos')
            ->where('channels.0.overlays.2.slug', 'escalacao')
            ->where('channels.0.overlays.3.slug', 'tabela')
            ->where('channels.0.overlays.4.slug', 'patrocinadores')
            ->where('channels.0.overlays.5.slug', 'prejogo')
            ->where('channels.0.overlays.6.slug', 'comparativo')
            ->where('channels.0.overlays.7.slug', 'cartela')
            ->where('channels.0.overlays.8.slug', 'resumo')
            ->where('channels.0.overlays.9.slug', 'chaveamento')
            ->has('channels.0.overlays', 10)
            ->where('channels.0.appearance.preset', 'arena')
            ->where('channels.0.lineup_settings.presentation', 'sequence')
            ->where('channels.0.lineup_settings.photo_person_ids', [])
            ->where('channels.0.has_control_token', true)
            ->where('issued_control.channel_id', $channel->id)
            ->has('issued_control.token')
            ->where('channels.0.layers', ['lineup' => false, 'standings' => false, 'scoreboard' => true, 'lower_third' => true]));

        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->where('issued_control', null));

        $this->post('/painel/broadcast', ['name' => 'Principal'])->assertSessionHasErrors('name');
        $this->delete("/painel/broadcast/{$channel->id}")->assertSessionHas('success');
        expect(OverlayChannel::query()->count())->toBe(0);
    });

    it('lets the operator put a game on air and toggle layers, and broadcasts the new state', function () {
        ['game' => $game, 'hp' => $hp] = sheetGame();
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayUpdated::class]);

        $this->patch("/painel/broadcast/{$channel->id}", [
            'game_id' => $game->id,
            'layers' => ['lineup' => true, 'inventada' => true],
            'appearance' => ['accent_color' => '#ff5500', 'position' => 'top-right', 'motion' => 'subtle'],
            'lineup_settings' => ['presentation' => 'formation', 'list_seconds' => 9, 'photo_person_ids' => [$hp[0]]],
        ])
            ->assertSessionHas('success');

        $channel->refresh();
        expect($channel->game_id)->toBe($game->id)
            ->and($channel->layers)->toBe(['lineup' => true])
            ->and($channel->appearance['accent_color'])->toBe('#ff5500')
            ->and($channel->appearance['position'])->toBe('top-right')
            ->and($channel->lineup_settings['presentation'])->toBe('formation')
            ->and($channel->lineup_settings['list_seconds'])->toBe(9)
            ->and($channel->lineup_settings['photo_person_ids'])->toBe([$hp[0]]);
        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->where('channels.0.layers', ['lineup' => true, 'standings' => false, 'scoreboard' => true, 'lower_third' => true]));
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['lineup'] !== null && $event->state['game']['id'] === $game->id);

        $this->patch("/painel/broadcast/{$channel->id}", ['layers' => ['lineup' => false]]);
        $this->patch("/painel/broadcast/{$channel->id}", ['game_id' => null]);

        expect($channel->fresh()->layers)->toBe(['lineup' => false])
            ->and($channel->fresh()->game_id)->toBeNull()
            ->and($channel->fresh()->lineup_settings['photo_person_ids'])->toBe([]);
    });

    it('accepts the classic and minimal presets and the bottom positions, and refuses unknown ones', function () {
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Operator));

        foreach (['classic', 'minimal', 'arena'] as $preset) {
            foreach (['bottom-left', 'bottom-center', 'bottom-right'] as $position) {
                $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['preset' => $preset, 'position' => $position]])->assertSessionHasNoErrors();
                expect($channel->fresh()->appearance['preset'])->toBe($preset)->and($channel->fresh()->appearance['position'])->toBe($position);
            }
        }

        $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['preset' => 'neon']])->assertSessionHasErrors('appearance.preset');
        $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['position' => 'center']])->assertSessionHasErrors('appearance.position');
    });

    it('lets the operator pick the scoreboard layout, tells the OBS and refuses unknown ones', function () {
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayUpdated::class]);

        expect(OverlayState::for($channel)['appearance']['layout'])->toBe('bar');

        foreach (['stacked', 'compact', 'wide', 'bar'] as $layout) {
            $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['layout' => $layout]])->assertSessionHasNoErrors();
            expect($channel->fresh()->appearance['layout'])->toBe($layout);
        }
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['appearance']['layout'] === 'wide');

        $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['layout' => 'gigante']])->assertSessionHasErrors('appearance.layout');
        expect($channel->fresh()->appearance['layout'])->toBe('bar');

        $channel->update(['appearance' => ['layout' => 'gigante']]);
        expect(OverlayState::for($channel->fresh())['appearance']['layout'])->toBe('bar');
    });

    it('keeps the layout in a saved scene', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $channel = channelFor();
        $channel->update(['appearance' => ['layout' => 'stacked']]);

        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'Canto', 'channel_id' => $channel->id])->assertSessionHas('success');
        $target = channelFor();
        $this->patch(route('panel.broadcast.scenes.apply', ['channel' => $target, 'scene' => OverlayScene::query()->firstOrFail()]))->assertSessionHas('success');

        expect($target->fresh()->appearance['layout'])->toBe('stacked');
    });

    it('refuses games from another organization', function () {
        $channel = channelFor();
        $foreign = Game::factory()->create();
        Game::query()->whereKey($foreign->id)->update(['organization_id' => Organization::factory()->create(['slug' => 'outra'])->id]);
        $this->actingAs(panelUser(Role::Admin));

        $this->patch("/painel/broadcast/{$channel->id}", ['game_id' => $foreign->id])->assertSessionHasErrors('game_id');
    });

    it('renews the token and the old url stops working', function () {
        $channel = channelFor();
        $old = $channel->token;
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/broadcast/{$channel->id}/regenerate")->assertSessionHas('success');

        expect($channel->fresh()->token)->not->toBe($old);
        auth()->logout();
        $this->get("/overlay/{$old}/placar")->assertNotFound();
        $this->get("/overlay/{$channel->fresh()->token}/placar")->assertOk();
    });

    it('renews the api control key and only stores its hash', function () {
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/broadcast/{$channel->id}/control-token")
            ->assertSessionHas('success')
            ->assertSessionHas('overlay_issued_control', fn (array $issued) => $issued['channel_id'] === $channel->id && strlen($issued['token']) === 64);

        $issued = session('overlay_issued_control');
        expect($channel->fresh()->control_token_hash)->toBe(OverlayChannel::controlTokenHash($issued['token']))
            ->and($channel->fresh()->toArray())->not->toHaveKeys(['control_token', 'control_token_hash']);
    });

    it('applies the permissions of each role', function () {
        $channel = channelFor();

        $this->actingAs(panelUser(Role::Viewer));
        $this->get('/painel/broadcast')->assertOk();
        $this->patch("/painel/broadcast/{$channel->id}", ['layers' => ['lineup' => true]])->assertSessionHas('error');
        $this->post('/painel/broadcast', ['name' => 'Novo'])->assertSessionHas('error');

        $this->actingAs(panelUser(Role::Operator));
        $this->post('/painel/broadcast', ['name' => 'Novo'])->assertSessionHas('error');
        $this->delete("/painel/broadcast/{$channel->id}")->assertSessionHas('error');
        $this->post("/painel/broadcast/{$channel->id}/regenerate")->assertSessionHas('error');

        expect(OverlayChannel::query()->count())->toBe(1)->and($channel->fresh()->layers)->toBe([]);
    });
});

describe('control api', function () {
    it('updates the on-air state and appearance with the private bearer key', function () {
        ['game' => $game, 'hp' => $hp] = sheetGame();
        $token = OverlayChannel::newControlToken();
        $channel = channelFor();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($token)]);
        Event::fake([OverlayUpdated::class]);

        $this->withToken($token)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", [
            'game_id' => $game->id,
            'layers' => ['lineup' => true],
            'appearance' => ['accent_color' => '#AABBCC', 'scale' => 1.25, 'motion' => 'none'],
            'lineup_settings' => ['presentation' => 'sequence', 'list_seconds' => 5, 'photo_person_ids' => [$hp[0]]],
        ])->assertOk()
            ->assertJsonPath('data.game.id', $game->id)
            ->assertJsonPath('data.layers.lineup', true)
            ->assertJsonPath('data.appearance.accent_color', '#aabbcc')
            ->assertJsonPath('data.appearance.scale', 1.25)
            ->assertJsonPath('data.lineup_settings.presentation', 'sequence')
            ->assertJsonPath('data.lineup_settings.list_seconds', 5)
            ->assertJsonMissingPath('data.lineup_settings.photo_person_ids')
            ->assertJsonMissingPath('data.control_token_hash');

        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['appearance']['motion'] === 'none');
    });

    it('refuses a missing, wrong or public viewing token', function () {
        $controlToken = OverlayChannel::newControlToken();
        $channel = channelFor();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($controlToken)]);

        $this->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['layers' => ['lineup' => true]])->assertUnauthorized();
        $this->withToken('wrong')->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['layers' => ['lineup' => true]])->assertUnauthorized();
        $this->withToken($channel->token)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['layers' => ['lineup' => true]])->assertUnauthorized();

        expect($channel->fresh()->layers)->toBe([]);
    });

    it('validates visual attributes and refuses games from another organization', function () {
        $token = OverlayChannel::newControlToken();
        $channel = channelFor();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($token)]);
        $foreign = Game::factory()->create();
        Game::query()->whereKey($foreign->id)->update(['organization_id' => Organization::factory()->create(['slug' => 'api-outra'])->id]);

        $this->withToken($token)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", [
            'game_id' => $foreign->id,
            'appearance' => ['accent_color' => 'red', 'motion' => 'explosive', 'scale' => 4],
            'lineup_settings' => ['presentation' => 'carousel', 'list_seconds' => 30],
        ])->assertUnprocessable()->assertJsonValidationErrors(['game_id', 'appearance.accent_color', 'appearance.motion', 'appearance.scale', 'lineup_settings.presentation', 'lineup_settings.list_seconds']);
    });

    it('refuses photo ids that are not in the game lineup', function () {
        ['game' => $game] = sheetGame();
        $token = OverlayChannel::newControlToken();
        $channel = channelFor($game);
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($token)]);
        $person = Person::factory()->create();

        $this->withToken($token)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", [
            'lineup_settings' => ['photo_person_ids' => [$person->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('lineup_settings.photo_person_ids.0');
    });
});

describe('sponsors', function () {
    function sponsorLogo(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 300, 120);
    }

    beforeEach(function () {
        Storage::fake('s3');
    });

    it('keeps the rotation off by default and sends no logos to the OBS while it is off', function () {
        $organizer = panelUser(Role::Organizer);
        $this->actingAs($organizer)->post(route('panel.broadcast.sponsors.store'), ['name' => 'Padaria Central', 'logo' => sponsorLogo()])->assertSessionHas('success');
        $channel = channelFor();

        expect(OverlayState::for($channel)['sponsors'])->toBe(['enabled' => false, 'seconds' => 8, 'items' => []]);

        $channel->update(['sponsor_settings' => ['enabled' => true, 'seconds' => 12]]);
        $sponsors = OverlayState::for($channel->fresh())['sponsors'];

        expect($sponsors['enabled'])->toBeTrue()
            ->and($sponsors['seconds'])->toBe(12)
            ->and($sponsors['items'])->toHaveCount(1)
            ->and($sponsors['items'][0]['name'])->toBe('Padaria Central')
            ->and($sponsors['items'][0]['logo_url'])->toContain('-512.webp')
            ->and(array_keys($sponsors['items'][0]))->toBe(['name', 'logo_url']);
    });

    it('lets the operator turn the rotation on and set the time, with limits, and tells the OBS', function () {
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayUpdated::class]);

        $this->patch("/painel/broadcast/{$channel->id}", ['sponsor_settings' => ['enabled' => true, 'seconds' => 15]])->assertSessionHasNoErrors();

        expect($channel->fresh()->sponsor_settings)->toBe(['enabled' => true, 'seconds' => 15]);
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['sponsors']['enabled'] === true);

        $this->patch("/painel/broadcast/{$channel->id}", ['sponsor_settings' => ['seconds' => 2]])->assertSessionHasErrors('sponsor_settings.seconds');
        $this->patch("/painel/broadcast/{$channel->id}", ['sponsor_settings' => ['seconds' => 31]])->assertSessionHasErrors('sponsor_settings.seconds');
    });

    it('registers, pauses and deletes sponsors, only for who manages competitions, and refreshes the open channels', function () {
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Operator))->post(route('panel.broadcast.sponsors.store'), ['name' => 'X', 'logo' => sponsorLogo()])->assertSessionHas('error');
        expect(OverlaySponsor::query()->count())->toBe(0);

        $this->actingAs(panelUser(Role::Organizer));
        Event::fake([OverlayUpdated::class]);
        $this->post(route('panel.broadcast.sponsors.store'), ['name' => 'Loja Azul', 'logo' => sponsorLogo()])->assertSessionHas('success');
        $sponsor = OverlaySponsor::query()->firstOrFail();
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->token === $channel->token);

        $channel->update(['sponsor_settings' => ['enabled' => true, 'seconds' => 8]]);
        $this->patch(route('panel.broadcast.sponsors.update', $sponsor), ['active' => false])->assertSessionHas('success');
        expect(OverlayState::for($channel->fresh())['sponsors']['items'])->toBe([]);

        $this->delete(route('panel.broadcast.sponsors.destroy', $sponsor))->assertSessionHas('success');
        expect(OverlaySponsor::query()->count())->toBe(0);
        Storage::disk('s3')->assertDirectoryEmpty("organizations/{$sponsor->organization_id}/sponsors");
    });

    it('refuses a missing logo, a file that is not an image and more sponsors than the limit', function () {
        $this->actingAs(panelUser(Role::Admin));

        $this->post(route('panel.broadcast.sponsors.store'), ['name' => 'Sem logo'])->assertSessionHasErrors('logo');
        $this->post(route('panel.broadcast.sponsors.store'), ['name' => 'PDF', 'logo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');

        foreach (range(1, OverlaySponsor::LIMIT) as $i) {
            $this->post(route('panel.broadcast.sponsors.store'), ['name' => "Patrocinador {$i}", 'logo' => sponsorLogo("l{$i}.png")]);
        }
        expect(OverlaySponsor::query()->count())->toBe(OverlaySponsor::LIMIT);

        $this->post(route('panel.broadcast.sponsors.store'), ['name' => 'De sobra', 'logo' => sponsorLogo()])->assertSessionHasErrors('logo');
    });

    it('is isolated by organization and listed on the broadcast screen', function () {
        $this->actingAs(panelUser(Role::Admin))->post(route('panel.broadcast.sponsors.store'), ['name' => 'Meu', 'logo' => sponsorLogo()]);
        $mine = OverlaySponsor::query()->firstOrFail();

        $other = Organization::factory()->create(['slug' => 'outra-org']);
        CurrentOrganization::using($other, function () {
            expect(OverlaySponsor::query()->count())->toBe(0);
        });

        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->has('sponsors', 1)->where('sponsors.0.id', $mine->id)->where('sponsor_limit', OverlaySponsor::LIMIT));
    });

    it('serves the sponsors screen at its own OBS URL', function () {
        $channel = channelFor();

        $this->get($channel->overlayUrl('patrocinadores'))->assertOk()->assertInertia(fn ($page) => $page->component('Overlay/Sponsors')->has('state.sponsors'));
    });
});

describe('fonts', function () {
    function fontFile(string $name = 'Marca.woff2', string $magic = 'wOF2', int $size = 400): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $magic.str_repeat("\0", $size));
    }

    beforeEach(function () {
        Storage::fake('s3');
    });

    it('uses the system font by default and accepts the catalog fonts', function () {
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Operator));

        expect(OverlayState::for($channel)['appearance']['font'])->toBe('system')
            ->and(OverlayState::for($channel)['font_face'])->toBeNull();

        foreach (['bebas', 'oswald', 'playfair', 'system'] as $font) {
            $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['font' => $font]])->assertSessionHasNoErrors();
            expect($channel->fresh()->appearance['font'])->toBe($font);
        }

        $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['font' => 'comic-neue']])->assertSessionHasErrors('appearance.font');
    });

    it('stores a font by its bytes, not its name, and refuses anything else', function () {
        $this->actingAs(panelUser(Role::Organizer));

        $this->post(route('panel.broadcast.fonts.store'), ['name' => 'Marca', 'font' => fontFile()])->assertSessionHas('success');
        $font = OverlayFont::query()->firstOrFail();
        expect($font->format)->toBe('woff2')->and($font->name)->toBe('Marca');
        Storage::disk('s3')->assertExists($font->path);

        $this->post(route('panel.broadcast.fonts.store'), ['name' => 'Disfarçada', 'font' => UploadedFile::fake()->createWithContent('x.woff2', '%PDF-1.4 conteudo')])->assertSessionHasErrors('font');
        $this->post(route('panel.broadcast.fonts.store'), ['name' => 'Coleção', 'font' => fontFile('c.ttc', 'ttcf')])->assertSessionHasErrors('font');
        $this->post(route('panel.broadcast.fonts.store'), ['name' => 'Grande', 'font' => fontFile('g.woff2', 'wOF2', 3 * 1024 * 1024)])->assertSessionHasErrors('font');
        expect(OverlayFont::query()->count())->toBe(1);

        $this->post(route('panel.broadcast.fonts.store'), ['name' => 'TTF', 'font' => fontFile('a.ttf', "\x00\x01\x00\x00")]);
        $this->post(route('panel.broadcast.fonts.store'), ['name' => 'OTF', 'font' => fontFile('a.otf', 'OTTO')]);
        expect(OverlayFont::query()->pluck('format')->sort()->values()->all())->toBe(['otf', 'ttf', 'woff2']);
    });

    it('stops at the limit and only organizers manage fonts', function () {
        $this->actingAs(panelUser(Role::Operator))->post(route('panel.broadcast.fonts.store'), ['name' => 'X', 'font' => fontFile()])->assertSessionHas('error');
        expect(OverlayFont::query()->count())->toBe(0);

        $this->actingAs(panelUser(Role::Admin));
        foreach (range(1, OverlayFont::LIMIT) as $i) {
            $this->post(route('panel.broadcast.fonts.store'), ['name' => "Fonte {$i}", 'font' => fontFile("f{$i}.woff2")]);
        }
        expect(OverlayFont::query()->count())->toBe(OverlayFont::LIMIT);

        $this->post(route('panel.broadcast.fonts.store'), ['name' => 'De sobra', 'font' => fontFile()])->assertSessionHasErrors('font');
    });

    it('lets a channel use an own font, hands the OBS a same-origin address and serves the file', function () {
        $this->actingAs(panelUser(Role::Admin))->post(route('panel.broadcast.fonts.store'), ['name' => 'Marca', 'font' => fontFile()]);
        $font = OverlayFont::query()->firstOrFail();
        $channel = channelFor();

        $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['font' => $font->id]])->assertSessionHasNoErrors();

        $state = OverlayState::for($channel->fresh());
        expect($state['appearance']['font'])->toBe($font->id)
            ->and($state['font_face'])->toBe(['family' => 'OverlayCustom', 'url' => "/overlay/{$channel->token}/fonte/{$font->id}", 'format' => 'woff2']);

        $this->get($state['font_face']['url'])->assertOk()->assertHeader('Content-Type', 'font/woff2')->assertHeader('X-Content-Type-Options', 'nosniff');
        expect(json_encode($state))->not->toContain($font->path);
    });

    it('refuses a font id that does not belong to the organization', function () {
        $other = Organization::factory()->create(['slug' => 'outra-fonte']);
        $foreign = CurrentOrganization::using($other, fn () => OverlayFont::query()->create(['name' => 'Alheia', 'path' => 'x.woff2', 'format' => 'woff2', 'bytes' => 10]));
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Admin));

        $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['font' => $foreign->id]])->assertSessionHasErrors('appearance.font');
        expect($channel->fresh()->appearance['font'] ?? 'system')->toBe('system');
    });

    it('falls back to the default font and refreshes the OBS when a used font is deleted', function () {
        $this->actingAs(panelUser(Role::Admin))->post(route('panel.broadcast.fonts.store'), ['name' => 'Marca', 'font' => fontFile()]);
        $font = OverlayFont::query()->firstOrFail();
        $channel = channelFor();
        $this->patch("/painel/broadcast/{$channel->id}", ['appearance' => ['font' => $font->id]]);
        Event::fake([OverlayUpdated::class]);

        $this->delete(route('panel.broadcast.fonts.destroy', $font))->assertSessionHas('success');

        expect(OverlayFont::query()->count())->toBe(0)
            ->and($channel->fresh()->appearance['font'])->toBe('system')
            ->and(OverlayState::for($channel->fresh())['font_face'])->toBeNull();
        Storage::disk('s3')->assertMissing($font->path);
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['appearance']['font'] === 'system');
    });

    it('answers 404 for a font file that is gone and lists the fonts on the broadcast screen', function () {
        $this->actingAs(panelUser(Role::Admin))->post(route('panel.broadcast.fonts.store'), ['name' => 'Marca', 'font' => fontFile()]);
        $font = OverlayFont::query()->firstOrFail();
        $channel = channelFor();

        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->has('fonts', 1)->where('fonts.0.name', 'Marca')->where('font_limit', OverlayFont::LIMIT));

        Storage::disk('s3')->delete($font->path);
        $this->get("/overlay/{$channel->token}/fonte/{$font->id}")->assertNotFound();
    });
});

describe('show and hide commands', function () {
    it('starts with the scoreboard and the lower thirds on air, and the lineup and the standings hidden', function () {
        $channel = channelFor();

        expect($channel->layerStates())->toBe(['lineup' => false, 'standings' => false, 'scoreboard' => true, 'lower_third' => true])
            ->and(OverlayState::for($channel)['layers'])->toBe(['lineup' => false, 'standings' => false, 'scoreboard' => true, 'lower_third' => true]);
    });

    it('lets the operator hide and show the scoreboard even with no game, and tells the OBS at once', function () {
        $channel = channelFor();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayUpdated::class]);

        $this->patch("/painel/broadcast/{$channel->id}", ['layers' => ['scoreboard' => false, 'lower_third' => false]])->assertSessionHasNoErrors();

        expect($channel->fresh()->layerOn('scoreboard'))->toBeFalse()->and($channel->fresh()->layerOn('lower_third'))->toBeFalse();
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['layers']['scoreboard'] === false && $event->state['layers']['lower_third'] === false);

        $this->patch("/painel/broadcast/{$channel->id}", ['layers' => ['scoreboard' => true]]);
        expect($channel->fresh()->layerOn('scoreboard'))->toBeTrue()->and($channel->fresh()->layerOn('lower_third'))->toBeFalse();
    });

    it('accepts the same commands from the control API and keeps the unknown layers out', function () {
        $channel = channelFor();
        $key = OverlayChannel::newControlToken();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);

        $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['layers' => ['scoreboard' => false, 'inventada' => true]])
            ->assertOk()
            ->assertJsonPath('data.layers.scoreboard', false)
            ->assertJsonMissingPath('data.layers.inventada');
    });
});

describe('scenes', function () {
    function styledChannel(): OverlayChannel
    {
        $channel = channelFor(null, ['lineup' => true]);
        $channel->update([
            'appearance' => ['preset' => 'classic', 'accent_color' => '#ff5500', 'background_color' => '#101010', 'text_color' => '#ffffff', 'position' => 'bottom-center', 'scale' => 1.2, 'motion' => 'subtle', 'font' => 'bebas'],
            'sponsor_settings' => ['enabled' => true, 'seconds' => 12],
            'lineup_settings' => ['presentation' => 'formation', 'list_seconds' => 9, 'photo_person_ids' => ['01jabc']],
        ]);

        return $channel;
    }

    it('saves the visual of a channel as a scene, without the game or the chosen photos', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $channel = styledChannel();

        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'Pré-jogo', 'channel_id' => $channel->id])->assertSessionHas('success');

        $scene = OverlayScene::query()->firstOrFail();
        expect($scene->name)->toBe('Pré-jogo')
            ->and($scene->config['appearance']['preset'])->toBe('classic')
            ->and($scene->config['appearance']['font'])->toBe('bebas')
            ->and($scene->config['layers'])->toBe(['lineup' => true, 'standings' => false, 'scoreboard' => true, 'lower_third' => true])
            ->and($scene->config['sponsor_settings'])->toBe(['enabled' => true, 'seconds' => 12])
            ->and($scene->config['lineup_settings'])->toBe(['presentation' => 'formation', 'list_seconds' => 9, 'team' => 'home', 'individual_mode' => 'auto', 'individual_seconds' => 6, 'individual_scope' => 'starters', 'sponsor_banner' => false])
            ->and(array_keys($scene->config))->not->toContain('game_id')
            ->and(json_encode($scene->config))->not->toContain('01jabc');
    });

    it('applies a scene with one click to another channel, keeping its game, and tells the OBS', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'Jogo', 'channel_id' => styledChannel()->id]);
        $scene = OverlayScene::query()->firstOrFail();

        ['game' => $game] = sheetGame();
        $target = channelFor($game);
        Event::fake([OverlayUpdated::class]);

        $this->actingAs(panelUser(Role::Operator))->patch(route('panel.broadcast.scenes.apply', ['channel' => $target, 'scene' => $scene]))->assertSessionHas('success');

        $target->refresh();
        expect($target->game_id)->toBe($game->id)
            ->and($target->appearance['preset'])->toBe('classic')
            ->and($target->appearance['position'])->toBe('bottom-center')
            ->and($target->layers['lineup'])->toBeTrue()
            ->and($target->sponsor_settings)->toBe(['enabled' => true, 'seconds' => 12])
            ->and($target->lineup_settings['presentation'])->toBe('formation');
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->token === $target->token && $event->state['appearance']['preset'] === 'classic');
    });

    it('lets the API apply a scene and override a field on top of it', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'Intervalo', 'channel_id' => styledChannel()->id]);
        $scene = OverlayScene::query()->firstOrFail();
        $target = channelFor();
        $key = OverlayChannel::newControlToken();
        $target->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);

        $this->withHeader('Authorization', "Bearer {$key}")
            ->patchJson("/api/v1/overlay-channels/{$target->id}/state", ['scene' => $scene->id, 'appearance' => ['accent_color' => '#00ff00']])
            ->assertOk()
            ->assertJsonPath('data.appearance.preset', 'classic')
            ->assertJsonPath('data.appearance.accent_color', '#00ff00')
            ->assertJsonPath('data.sponsors.enabled', true);

        $this->withHeader('Authorization', "Bearer {$key}")->patchJson("/api/v1/overlay-channels/{$target->id}/state", ['scene' => 'inexistente'])->assertStatus(422);
    });

    it('only organizers save and delete scenes, operators apply them', function () {
        $channel = styledChannel();

        $this->actingAs(panelUser(Role::Operator))->post(route('panel.broadcast.scenes.store'), ['name' => 'X', 'channel_id' => $channel->id])->assertSessionHas('error');
        expect(OverlayScene::query()->count())->toBe(0);

        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.broadcast.scenes.store'), ['name' => 'Y', 'channel_id' => $channel->id]);
        $scene = OverlayScene::query()->firstOrFail();

        $this->actingAs(panelUser(Role::Viewer))->patch(route('panel.broadcast.scenes.apply', ['channel' => $channel, 'scene' => $scene]))->assertSessionHas('error');
        $this->actingAs(panelUser(Role::Operator))->delete(route('panel.broadcast.scenes.destroy', $scene))->assertSessionHas('error');
        expect(OverlayScene::query()->count())->toBe(1);

        $this->actingAs(panelUser(Role::Organizer))->delete(route('panel.broadcast.scenes.destroy', $scene))->assertSessionHas('success');
        expect(OverlayScene::query()->count())->toBe(0);
    });

    it('needs a unique name, stops at the limit and never mixes organizations', function () {
        $this->actingAs(panelUser(Role::Admin));
        $channel = channelFor();

        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'Única', 'channel_id' => $channel->id]);
        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'Única', 'channel_id' => $channel->id])->assertSessionHasErrors('name');
        $this->post(route('panel.broadcast.scenes.store'), ['name' => '', 'channel_id' => $channel->id])->assertSessionHasErrors('name');

        foreach (range(2, OverlayScene::LIMIT) as $i) {
            $this->post(route('panel.broadcast.scenes.store'), ['name' => "Cena {$i}", 'channel_id' => $channel->id]);
        }
        expect(OverlayScene::query()->count())->toBe(OverlayScene::LIMIT);
        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'De sobra', 'channel_id' => $channel->id])->assertSessionHasErrors('name');

        $other = Organization::factory()->create(['slug' => 'outra-cena']);
        $foreignChannel = CurrentOrganization::using($other, fn () => channelFor());
        $this->post(route('panel.broadcast.scenes.store'), ['name' => 'Alheio', 'channel_id' => $foreignChannel->id])->assertSessionHasErrors('channel_id');
        CurrentOrganization::using($other, function () {
            expect(OverlayScene::query()->count())->toBe(0);
        });

        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->has('scenes', OverlayScene::LIMIT)->where('scene_limit', OverlayScene::LIMIT));
    });
});

describe('lineup presentation', function () {
    function showChannel(array $lineupSettings = []): array
    {
        ['game' => $game, 'hp' => $hp, 'ap' => $ap] = sheetGame();
        $channel = channelFor($game, ['lineup' => true]);
        $channel->update(['lineup_settings' => array_merge(['presentation' => 'show', 'individual_mode' => 'manual', 'step' => 0, 'anchor_ms' => 0], $lineupSettings)]);

        return [$channel->fresh(), $game, $hp, $ap];
    }

    it('counts the players the individual presentation walks', function () {
        [$channel] = showChannel();

        // sheetGame: 11 titulares + 3 reservas por equipe, sem técnico lançado.
        expect(OverlayState::individualCount($channel))->toBe(11);

        $channel->update(['lineup_settings' => ['individual_scope' => 'all']]);
        expect(OverlayState::individualCount($channel->fresh()))->toBe(14);
    });

    it('has the timeline steps, starts and commands in one place', function () {
        expect(OverlayLineupTimeline::steps('show', 11))->toBe(12)
            ->and(OverlayLineupTimeline::steps('individual', 11))->toBe(11)
            ->and(OverlayLineupTimeline::steps('individual', 0))->toBe(1)
            ->and(OverlayLineupTimeline::steps('cards', 11))->toBe(1)
            ->and(OverlayLineupTimeline::starts('show', 5, 6, 3))->toBe([0, 5000, 11000, 17000])
            ->and(OverlayLineupTimeline::starts('individual', 5, 6, 3))->toBe([0, 6000, 12000]);

        $manual = OverlayLineupSettings::normalize(['presentation' => 'show', 'individual_mode' => 'manual', 'step' => 2]);
        expect(OverlayLineupTimeline::apply('next', $manual, 3, 50_000)['step'])->toBe(3)
            ->and(OverlayLineupTimeline::apply('previous', $manual, 3, 50_000)['step'])->toBe(1)
            ->and(OverlayLineupTimeline::apply('next', [...$manual, 'step' => 3], 3, 50_000)['step'])->toBe(0)
            ->and(OverlayLineupTimeline::apply('next', [...$manual, 'presentation' => 'individual', 'step' => 2], 3, 50_000)['step'])->toBe(2)
            ->and(OverlayLineupTimeline::apply('previous', [...$manual, 'step' => 0], 3, 50_000)['step'])->toBe(0)
            ->and(OverlayLineupTimeline::apply('reset', $manual, 3, 50_000))->toBe(['step' => 0, 'anchor_ms' => 50_000]);

        $auto = OverlayLineupSettings::normalize(['presentation' => 'show', 'individual_mode' => 'auto', 'list_seconds' => 5, 'individual_seconds' => 6, 'anchor_ms' => 100_000]);
        // 100_000 + 5_500 está na etapa 1: próximo leva o relógio para o início da etapa 2 (11 s), anterior para o início da 0.
        expect(OverlayLineupTimeline::apply('next', $auto, 3, 105_500)['anchor_ms'])->toBe(105_500 - 11_000)
            ->and(OverlayLineupTimeline::apply('previous', $auto, 3, 105_500)['anchor_ms'])->toBe(105_500);
    });

    it('moves the manual presentation with the operator commands, in real time, and tells the OBS', function () {
        [$channel] = showChannel();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayUpdated::class]);

        foreach ([1, 2, 3] as $expected) {
            $this->patch("/painel/broadcast/{$channel->id}", ['lineup_command' => 'next'])->assertSessionHasNoErrors();
            expect($channel->fresh()->lineup_settings['step'])->toBe($expected);
        }
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['lineup_settings']['step'] === 3);

        $this->patch("/painel/broadcast/{$channel->id}", ['lineup_command' => 'previous']);
        expect($channel->fresh()->lineup_settings['step'])->toBe(2);

        $this->patch("/painel/broadcast/{$channel->id}", ['lineup_command' => 'reset']);
        expect($channel->fresh()->lineup_settings['step'])->toBe(0)->and($channel->fresh()->lineup_settings['anchor_ms'])->toBeGreaterThan(0);

        $this->patch("/painel/broadcast/{$channel->id}", ['lineup_command' => 'explodir'])->assertSessionHasErrors('lineup_command');
    });

    it('lets the control API pass the players and never accepts step or anchor directly', function () {
        [$channel] = showChannel();
        $key = OverlayChannel::newControlToken();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);

        $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['lineup_command' => 'next'])
            ->assertOk()->assertJsonPath('data.lineup_settings.step', 1);

        $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['lineup_settings' => ['step' => 9, 'anchor_ms' => 5]])->assertOk();
        expect($channel->fresh()->lineup_settings['step'])->toBe(1)->and($channel->fresh()->lineup_settings['anchor_ms'])->toBe(0);
    });

    it('restarts the presentation when the team, the mode, the times or the game change, or the lineup goes on air', function () {
        [$channel] = showChannel(['step' => 5, 'anchor_ms' => 1000]);
        $this->actingAs(panelUser(Role::Operator));

        $this->patch("/painel/broadcast/{$channel->id}", ['lineup_settings' => ['team' => 'away']])->assertSessionHasNoErrors();
        expect($channel->fresh()->lineup_settings['step'])->toBe(0)->and($channel->fresh()->lineup_settings['anchor_ms'])->toBeGreaterThan(1000);

        $channel->update(['lineup_settings' => array_merge($channel->fresh()->lineup_settings, ['step' => 4, 'anchor_ms' => 1000])]);
        $this->patch("/painel/broadcast/{$channel->id}", ['lineup_settings' => ['sponsor_banner' => true]]);
        expect($channel->fresh()->lineup_settings['step'])->toBe(4)->and($channel->fresh()->lineup_settings['sponsor_banner'])->toBeTrue();

        $this->patch("/painel/broadcast/{$channel->id}", ['layers' => ['lineup' => false]]);
        $this->patch("/painel/broadcast/{$channel->id}", ['layers' => ['lineup' => true]]);
        expect($channel->fresh()->lineup_settings['step'])->toBe(0);
    });

    it('validates the new lineup options', function () {
        [$channel] = showChannel();
        $this->actingAs(panelUser(Role::Operator));

        $this->patch("/painel/broadcast/{$channel->id}", ['lineup_settings' => ['presentation' => 'individual', 'team' => 'away', 'individual_mode' => 'manual', 'individual_seconds' => 12, 'individual_scope' => 'all', 'sponsor_banner' => true]])->assertSessionHasNoErrors();
        expect($channel->fresh()->lineup_settings)->toMatchArray(['presentation' => 'individual', 'team' => 'away', 'individual_mode' => 'manual', 'individual_seconds' => 12, 'individual_scope' => 'all', 'sponsor_banner' => true]);

        $this->patch("/painel/broadcast/{$channel->id}", ['lineup_settings' => ['team' => 'neutro', 'individual_mode' => 'turbo', 'individual_seconds' => 2, 'individual_scope' => 'ninguem']])
            ->assertSessionHasErrors(['lineup_settings.team', 'lineup_settings.individual_mode', 'lineup_settings.individual_seconds', 'lineup_settings.individual_scope']);
    });

    it('sends a large photo only for the athletes the operator enabled, and the sponsors for the banner', function () {
        [$channel, , $hp] = showChannel(['photo_person_ids' => []]);
        Storage::fake('s3-private');
        Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://private.test/{$path}");
        Person::query()->whereIn('id', [$hp[0], $hp[1]])->update(['photo_path' => 'people/test/photo']);
        $channel->update(['lineup_settings' => array_merge($channel->lineup_settings, ['photo_person_ids' => [$hp[0]]])]);

        $state = OverlayState::for($channel->fresh());
        $first = $state['lineup']['home']['starters'][0];
        expect($first['photo_url'])->toContain('-96.webp')->and($first['photo_large_url'])->toContain('-400.webp')
            ->and($state['lineup']['home']['starters'][1]['photo_large_url'])->toBeNull();

        Storage::fake('s3');
        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.broadcast.sponsors.store'), ['name' => 'Padaria', 'logo' => UploadedFile::fake()->image('logo.png', 300, 120)])->assertSessionHas('success');
        expect(OverlayState::for($channel->fresh())['sponsors']['items'])->toBe([]);

        $channel->update(['lineup_settings' => array_merge($channel->fresh()->lineup_settings, ['sponsor_banner' => true])]);
        $sponsors = OverlayState::for($channel->fresh())['sponsors'];
        expect($sponsors['enabled'])->toBeFalse()->and($sponsors['items'])->toHaveCount(1)->and($sponsors['items'][0]['name'])->toBe('Padaria');
    });
});

<?php

use App\Modules\Broadcast\Events\OverlayLowerThird;
use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

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
        ])
        ->and($state['lineup_settings'])->toBe(['presentation' => 'sequence', 'list_seconds' => 6])
        ->and($state['game']['id'])->toBe($game->id)
        ->and($state['game']['home']['color'])->toBe('#ff0000')
        ->and($state['game']['clock']['state'])->toBe('not_started')
        ->and($state['layers'])->toBe(['lineup' => false, 'standings' => false])
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

    expect(OverlayState::for(channelFor($game, ['lineup' => true]))['layers'])->toBe(['lineup' => true, 'standings' => false]);

    expect($lineup['home']['starters'])->toHaveCount(11)
        ->and($lineup['home']['bench'])->toHaveCount(3)
        ->and($lineup['home']['starters'][0])->toBe([
            'shirt' => 1, 'name' => 'Rafa', 'position' => null, 'position_label' => null, 'x' => null, 'y' => null,
            'captain' => false, 'photo_url' => null,
        ])
        // Só dados visuais indispensáveis (nada de IDs, cartões, documentos ou nascimento). Confere as chaves: os nomes sorteados
        // pela fábrica podem conter "card" (ex.: Ricardo), então buscar texto no JSON era instável.
        ->and(collect([...$lineup['home']['starters'], ...$lineup['away']['starters']])->every(fn ($player) => array_keys($player) === ['shirt', 'name', 'position', 'position_label', 'x', 'y', 'captain', 'photo_url']))->toBeTrue()
        ->and(collect([...$lineup['home']['bench'], ...$lineup['away']['bench']])->every(fn ($player) => array_keys($player) === ['shirt', 'name', 'captain', 'photo_url']))->toBeTrue();
});

it('only publishes a short signed photo url for athletes explicitly enabled on the channel', function () {
    ['game' => $game, 'hp' => $hp] = sheetGame();
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://private.test/{$path}");
    Person::query()->whereIn('id', [$hp[0], $hp[1]])->update(['photo_path' => 'people/test/photo']);

    $channel = channelFor($game, ['lineup' => true]);
    $channel->update(['lineup_settings' => ['presentation' => 'formation', 'list_seconds' => 8, 'photo_person_ids' => [$hp[0]]]]);
    $state = OverlayState::for($channel->fresh());

    expect($state['lineup_settings'])->toBe(['presentation' => 'formation', 'list_seconds' => 8])
        ->and($state['lineup']['home']['starters'][0]['photo_url'])->toStartWith('https://private.test/')
        ->and($state['lineup']['home']['starters'][1]['photo_url'])->toBeNull()
        ->and(json_encode($state))->not->toContain($hp[0])->not->toContain('photo_person_ids');
});

it('shows the standings of the league or group of the game and nothing for knockouts', function () {
    ['game' => $game] = sheetGame();
    $state = OverlayState::for(channelFor($game, ['standings' => true]));

    expect($state['standings']['rows'])->toHaveCount(2)
        ->and(collect($state['standings']['rows'])->every(fn (array $row) => $row['highlight']))->toBeTrue();

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
    Person::query()->whereKey($hp[0])->update(['nickname' => 'Rafa']);
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
            ->has('channels.0.overlays', 4)
            ->where('channels.0.appearance.preset', 'arena')
            ->where('channels.0.lineup_settings.presentation', 'sequence')
            ->where('channels.0.lineup_settings.photo_person_ids', [])
            ->where('channels.0.has_control_token', true)
            ->where('appearance_presets', [
                ['value' => 'arena', 'label' => 'Arena'],
                ['value' => 'studio', 'label' => 'Studio'],
            ])
            ->where('issued_control.channel_id', $channel->id)
            ->has('issued_control.token')
            ->where('channels.0.layers', ['lineup' => false, 'standings' => false]));

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
            'appearance' => ['preset' => 'studio', 'accent_color' => '#ff5500', 'position' => 'top-right', 'motion' => 'subtle'],
            'lineup_settings' => ['presentation' => 'formation', 'list_seconds' => 9, 'photo_person_ids' => [$hp[0]]],
        ])
            ->assertSessionHas('success');

        $channel->refresh();
        expect($channel->game_id)->toBe($game->id)
            ->and($channel->layers)->toBe(['lineup' => true])
            ->and($channel->appearance['preset'])->toBe('studio')
            ->and($channel->appearance['accent_color'])->toBe('#ff5500')
            ->and($channel->appearance['position'])->toBe('top-right')
            ->and($channel->lineup_settings)->toBe(['presentation' => 'formation', 'list_seconds' => 9, 'photo_person_ids' => [$hp[0]]]);
        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->where('channels.0.layers', ['lineup' => true, 'standings' => false]));
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['lineup'] !== null && $event->state['game']['id'] === $game->id);

        $this->patch("/painel/broadcast/{$channel->id}", ['layers' => ['lineup' => false]]);
        $this->patch("/painel/broadcast/{$channel->id}", ['game_id' => null]);

        expect($channel->fresh()->layers)->toBe(['lineup' => false])
            ->and($channel->fresh()->game_id)->toBeNull()
            ->and($channel->fresh()->lineup_settings['photo_person_ids'])->toBe([]);
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
            'appearance' => ['preset' => 'studio', 'accent_color' => '#AABBCC', 'scale' => 1.25, 'motion' => 'none'],
            'lineup_settings' => ['presentation' => 'sequence', 'list_seconds' => 5, 'photo_person_ids' => [$hp[0]]],
        ])->assertOk()
            ->assertJsonPath('data.game.id', $game->id)
            ->assertJsonPath('data.layers.lineup', true)
            ->assertJsonPath('data.appearance.preset', 'studio')
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
            'appearance' => ['preset' => 'television', 'accent_color' => 'red', 'motion' => 'explosive', 'scale' => 4],
            'lineup_settings' => ['presentation' => 'carousel', 'list_seconds' => 30],
        ])->assertUnprocessable()->assertJsonValidationErrors(['game_id', 'appearance.preset', 'appearance.accent_color', 'appearance.motion', 'appearance.scale', 'lineup_settings.presentation', 'lineup_settings.list_seconds']);
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

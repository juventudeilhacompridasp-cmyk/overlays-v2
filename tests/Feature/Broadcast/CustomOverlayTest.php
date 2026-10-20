<?php

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayCustomAsset;
use App\Modules\Broadcast\Models\OverlayCustomItem;
use App\Modules\Broadcast\Models\OverlaySponsor;
use App\Modules\Broadcast\Support\OverlayCustomElements;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function customChannel(): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => null, 'layers' => []]);
}

/** @return array<string, mixed> */
function customBody(string $name = 'Lower third', array $elements = [['type' => 'text', 'text' => '{home.name}', 'x' => 5, 'y' => 6]]): array
{
    return ['name' => $name, 'width' => 1200, 'height' => 260, 'elements' => $elements];
}

describe('sanitizing', function () {
    it('keeps every value inside its limits and falls back to the defaults', function () {
        $e = OverlayCustomElements::sanitize([[
            'id' => 'A b!c', 'type' => 'unknown', 'x' => 9999, 'y' => -9999, 'w' => 0, 'h' => 'x', 'rotation' => 900, 'opacity' => -5, 'size' => 1, 'weight' => 123,
            'color' => 'vermelho', 'fill' => 'red', 'fill2' => '#ABCDEF', 'align' => 'justify', 'shadow' => 'gigante', 'animIn' => 'explodir', 'animOut' => 'voar',
            'duration' => 99999, 'delay' => -1, 'name' => "linha\ndois", 'text' => "oi\nmundo\x07",
        ]])[0];

        expect($e['id'])->toBe('Abc')->and($e['type'])->toBe('text')
            ->and($e)->toMatchArray([
                'x' => 200, 'y' => -100, 'w' => 0.5, 'h' => 12, 'rotation' => 360, 'opacity' => 0, 'size' => 6, 'weight' => 700, 'color' => '#ffffff', 'fill' => '', 'fill2' => '#abcdef',
                'align' => 'left', 'shadow' => 'none', 'animIn' => 'fade', 'animOut' => 'same', 'duration' => 5000, 'delay' => 0,
            ])
            ->and($e['name'])->not->toContain("\n")->and($e['text'])->toBe("oi\nmundo");
    });

    it('keeps a secure HTTPS video element for the OBS browser source', function () {
        $element = OverlayCustomElements::sanitize([['type' => 'video', 'src' => 'https://cdn.example.com/opening.mp4']])[0];

        expect($element['type'])->toBe('video')->and($element['src'])->toBe('https://cdn.example.com/opening.mp4');
    });

    it('caps the list at 60 elements, keeps ids unique and ignores junk entries', function () {
        $many = array_fill(0, 80, ['type' => 'shape', 'id' => 'same']);
        $clean = OverlayCustomElements::sanitize([...$many, 'lixo', 7]);

        expect($clean)->toHaveCount(60)->and(collect($clean)->pluck('id')->unique())->toHaveCount(60);
        expect(OverlayCustomElements::sanitize('nada'))->toBe([]);
    });

    it('only accepts https, game tokens or a sponsor reference as the image source', function () {
        $src = fn (string $value) => OverlayCustomElements::sanitize([['type' => 'image', 'src' => $value]])[0]['src'];

        expect($src('https://site.com/a.png'))->toBe('https://site.com/a.png')
            ->and($src('token:home.logo'))->toBe('token:home.logo')
            ->and($src('token:sponsor'))->toBe('token:sponsor')
            ->and($src('sponsor:01JABCDEF0123456789XYZ0001'))->toBe('sponsor:01JABCDEF0123456789XYZ0001')
            ->and($src('http://site.com/a.png'))->toBe('')
            ->and($src('javascript:alert(1)'))->toBe('')
            ->and($src('data:image/png;base64,AAAA'))->toBe('')
            ->and($src('/etc/passwd'))->toBe('')
            ->and($src('token:other'))->toBe('')
            ->and($src('https://exemplo.com/a b.png'))->toBe('');
    });

    it('limits the canvas size', function () {
        expect(OverlayCustomElements::size(99999, 1920))->toBe(3840)->and(OverlayCustomElements::size(5, 1920))->toBe(200)->and(OverlayCustomElements::size('x', 1080))->toBe(1080);
    });
});

describe('panel', function () {
    it('uploads an optimized image for the Builder', function () {
        Storage::fake('s3');
        $this->actingAs(panelUser(Role::Organizer));

        $response = $this->postJson(route('panel.broadcast.custom.assets.store'), ['file' => UploadedFile::fake()->image('placa.png', 400, 200)]);

        $response->assertOk()->assertJsonStructure(['id', 'name', 'url']);
        $asset = OverlayCustomAsset::query()->firstOrFail();
        expect($asset->variant_size)->toBe(OverlayCustomAsset::SIZE)
            ->and($asset->path)->toContain('/overlay-assets/image-');
        Storage::disk('s3')->assertExists($asset->path.'-'.OverlayCustomAsset::SIZE.'.webp');
    });

    it('creates, edits, duplicates and deletes an overlay, and tells the OBS', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $channel = customChannel();
        Event::fake([OverlayUpdated::class]);

        $this->post(route('panel.broadcast.custom.store'), customBody())->assertSessionHasNoErrors()->assertSessionHas('success');
        $item = OverlayCustomItem::query()->firstOrFail();
        expect($item->width)->toBe(1200)->and($item->elements)->toHaveCount(1)->and($item->elements[0]['text'])->toBe('{home.name}');
        Event::assertDispatched(OverlayUpdated::class);

        $this->patch(route('panel.broadcast.custom.update', $item), customBody('Renomeado', [['type' => 'shape'], ['type' => 'text']]))->assertSessionHasNoErrors();
        expect($item->fresh()->name)->toBe('Renomeado')->and($item->fresh()->elements)->toHaveCount(2);

        $this->post(route('panel.broadcast.custom.duplicate', $item))->assertSessionHas('success');
        $this->post(route('panel.broadcast.custom.duplicate', $item));
        expect(OverlayCustomItem::query()->orderBy('name')->pluck('name')->all())->toBe(['Renomeado', 'Renomeado (cópia 2)', 'Renomeado (cópia)']);

        // ligado no canal: excluir tira do ar
        $this->actingAs(panelUser(Role::Operator))->patch("/painel/broadcast/{$channel->id}", ['custom_live' => [$item->id => true]])->assertSessionHasNoErrors();
        expect($channel->fresh()->customLive())->toBe([$item->id => true]);
        $this->actingAs(panelUser(Role::Organizer))->delete(route('panel.broadcast.custom.destroy', $item))->assertSessionHas('success');
        expect(OverlayCustomItem::query()->whereKey($item->id)->exists())->toBeFalse()->and($channel->fresh()->customLive())->toBe([]);
    });

    it('refuses a repeated name, an empty name and more overlays than the limit', function () {
        $this->actingAs(panelUser(Role::Organizer));

        $this->post(route('panel.broadcast.custom.store'), customBody('Único'))->assertSessionHasNoErrors();
        $this->post(route('panel.broadcast.custom.store'), customBody('Único'))->assertSessionHasErrors('name');
        $this->post(route('panel.broadcast.custom.store'), customBody(''))->assertSessionHasErrors('name');
        $this->post(route('panel.broadcast.custom.store'), ['name' => 'Grande', 'width' => 99999])->assertSessionHasErrors('width');

        foreach (range(2, OverlayCustomItem::LIMIT) as $i) {
            $this->post(route('panel.broadcast.custom.store'), customBody("Overlay {$i}"));
        }
        expect(OverlayCustomItem::query()->count())->toBe(OverlayCustomItem::LIMIT);
        $this->post(route('panel.broadcast.custom.store'), customBody('De sobra'))->assertSessionHasErrors('name');
    });

    it('lets only who manages competitions edit, while the operator only shows and hides', function () {
        $this->actingAs(panelUser(Role::Admin))->post(route('panel.broadcast.custom.store'), customBody());
        $item = OverlayCustomItem::query()->firstOrFail();

        $this->actingAs(panelUser(Role::Operator));
        $this->post(route('panel.broadcast.custom.store'), customBody('Outro'));
        $this->patch(route('panel.broadcast.custom.update', $item), customBody('Hack'));
        $this->delete(route('panel.broadcast.custom.destroy', $item));
        expect(OverlayCustomItem::query()->count())->toBe(1)->and($item->fresh()->name)->toBe('Lower third');

        $this->actingAs(panelUser(Role::Viewer))->get('/painel/broadcast')->assertInertia(fn ($page) => $page->has('custom_items', 1)->where('custom_limit', OverlayCustomItem::LIMIT));
    });

    it('imports the exported file as a copy when the name exists, and sanitizes it', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $file = ['format' => 'juventude-overlay', 'version' => 1, 'overlay' => ['name' => 'Importado', 'width' => 800, 'height' => 450, 'elements' => [['type' => 'image', 'src' => 'javascript:alert(1)', 'x' => 9999]]]];

        $this->post(route('panel.broadcast.custom.import'), $file)->assertSessionHas('success');
        $this->post(route('panel.broadcast.custom.import'), $file)->assertSessionHas('success');

        $names = OverlayCustomItem::query()->orderBy('name')->pluck('name')->all();
        expect($names)->toBe(['Importado', 'Importado (2)'])->and(OverlayCustomItem::query()->first()->elements[0])->toMatchArray(['src' => '', 'x' => 200]);

        $this->post(route('panel.broadcast.custom.import'), ['format' => 'outro', 'version' => 1, 'overlay' => ['name' => 'x']])->assertSessionHasErrors('format');
        $this->post(route('panel.broadcast.custom.import'), ['format' => 'juventude-overlay', 'version' => 2, 'overlay' => ['name' => 'x']])->assertSessionHasErrors('version');
    });

    it('is isolated by organization', function () {
        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.broadcast.custom.store'), customBody());
        $mine = OverlayCustomItem::query()->firstOrFail();

        $other = Organization::factory()->create(['slug' => 'outra-org']);
        CurrentOrganization::using($other, fn () => expect(OverlayCustomItem::query()->count())->toBe(0));
        expect($mine->organization_id)->toBe(CurrentOrganization::id());
    });
});

describe('on air', function () {
    it('serves the page with the sanitized drawing and the sponsor logo already resolved', function () {
        Storage::fake('s3');
        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.broadcast.sponsors.store'), ['name' => 'Padaria', 'logo' => UploadedFile::fake()->image('logo.png', 300, 120)]);
        $sponsor = OverlaySponsor::query()->firstOrFail();
        $this->post(route('panel.broadcast.custom.store'), customBody('Selo', [['type' => 'image', 'src' => "sponsor:{$sponsor->id}"], ['type' => 'image', 'src' => 'sponsor:01JABCDEF0123456789XYZ0001']]));
        $item = OverlayCustomItem::query()->firstOrFail();
        $channel = customChannel();

        $this->get($channel->customUrl($item->id))->assertOk()->assertInertia(fn ($page) => $page->component('Overlay/Custom')
            ->where('item.name', 'Selo')->where('item.width', 1200)->where('state.custom_live', [])
            ->where('item.elements.0.src', fn ($src) => str_contains($src, '-512.webp') && ! str_contains($src, $sponsor->id))
            ->where('item.elements.1.src', ''));
    });

    it('turns on and off per channel, only for overlays that exist, and puts the sponsors in the state', function () {
        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.broadcast.custom.store'), customBody());
        $item = OverlayCustomItem::query()->firstOrFail();
        $channel = customChannel();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayUpdated::class]);

        expect((array) OverlayState::for($channel)['custom_live'])->toBe([]);

        $this->patch("/painel/broadcast/{$channel->id}", ['custom_live' => [$item->id => true, '01JXXXXXXXXXXXXXXXXXXXXXXX' => true]])->assertSessionHasNoErrors();
        expect((array) OverlayState::for($channel->fresh())['custom_live'])->toBe([$item->id => true]);
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => ((array) $event->state['custom_live']) === [$item->id => true]);

        $this->patch("/painel/broadcast/{$channel->id}", ['custom_live' => [$item->id => false]])->assertSessionHasNoErrors();
        expect($channel->fresh()->customLive())->toBe([]);

        $this->patch("/painel/broadcast/{$channel->id}", ['custom_live' => [$item->id => 'talvez']])->assertSessionHasErrors('custom_live.'.$item->id);
    });

    it('does not show an overlay of another organization at this address', function () {
        $other = Organization::factory()->create(['slug' => 'outra-org']);
        $foreign = CurrentOrganization::using($other, fn () => OverlayCustomItem::query()->create(['name' => 'Alheio', 'width' => 800, 'height' => 450, 'elements' => []]));
        $channel = customChannel();

        $this->get($channel->customUrl($foreign->id))->assertNotFound();
    });

    it('works through the control api', function () {
        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.broadcast.custom.store'), customBody());
        $item = OverlayCustomItem::query()->firstOrFail();
        $channel = customChannel();
        $key = OverlayChannel::newControlToken();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);

        $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['custom_live' => [$item->id => true]])->assertOk()->assertJsonPath("data.custom_live.{$item->id}", true);
    });
});

it('sends the standings rows for the table tokens only while a free overlay is live, without ids or logos', function () {
    ['game' => $game] = sheetGame();
    $item = OverlayCustomItem::query()->create(['name' => 'Tabela', 'width' => 1200, 'height' => 400, 'elements' => []]);
    $channel = OverlayChannel::query()->create(['name' => 'Canal', 'token' => OverlayChannel::newToken(), 'game_id' => $game->id, 'layers' => []]);

    expect(OverlayState::for($channel)['custom_table'])->toBeNull();

    $channel->update(['layers' => ['custom' => [$item->id => true]]]);
    $rows = OverlayState::for($channel->fresh())['custom_table'];

    expect($rows)->toBeArray()->toHaveCount(2)
        ->and(array_keys($rows[0]))->toBe(['position', 'team', 'points', 'played', 'won', 'drawn', 'lost', 'goal_difference']);
});

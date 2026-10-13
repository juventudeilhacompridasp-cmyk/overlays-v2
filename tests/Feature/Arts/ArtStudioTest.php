<?php

use App\Modules\Arts\Models\ArtAsset;
use App\Modules\Arts\Models\ArtTemplate;
use App\Modules\Arts\Support\ArtStyle;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Storage::fake('s3');
});

function artProps($response): array
{
    return $response->viewData('page')['props'];
}

describe('access', function () {
    it('is for any panel member, and nobody else', function () {
        foreach ([Role::Admin, Role::Organizer, Role::Operator, Role::Viewer] as $role) {
            test()->actingAs(panelUser($role))->get(route('panel.arts.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Panel/Arts/Index'));
        }

        test()->actingAs(managerFor(Team::factory()->create()))->get(route('panel.arts.index'))->assertForbidden();
        test()->actingAs(panelUser(Role::Athlete))->get(route('panel.arts.index'))->assertForbidden();
    });

    it('needs the arts feature in the package', function () {
        $admin = panelUser(Role::Admin);
        CurrentOrganization::get()->update(['plan' => Plan::Basic->value, 'features' => null]);

        test()->actingAs($admin)->get(route('panel.arts.index'))->assertForbidden();

        CurrentOrganization::get()->update(['plan' => Plan::Standard->value]);
        test()->actingAs($admin)->get(route('panel.arts.index'))->assertOk();
    });
});

describe('game list', function () {
    it('lists upcoming and recent games', function () {
        ['game' => $game] = sheetGame();
        $finished = sheetGame()['game'];
        $finished->update(['status' => GameStatus::Finished, 'home_score' => 2, 'away_score' => 1, 'finished_at' => now()]);

        $props = artProps(test()->actingAs(panelUser())->get(route('panel.arts.index')));

        expect(collect($props['upcoming'])->pluck('id'))->toContain($game->id)
            ->and(collect($props['recent'])->pluck('id'))->toContain($finished->id)
            ->and($props['presets'])->not->toBeEmpty()
            ->and($props['formats'])->toHaveCount(6)
            ->and(collect($props['kinds'])->pluck('value')->all())->toBe(['preview', 'result', 'lineup', 'round', 'cover']);
    });
});

describe('editor data', function () {
    it('sends the match data for the preview and result arts', function () {
        ['game' => $game] = sheetGame();
        $game->update(['status' => GameStatus::Finished, 'home_score' => 3, 'away_score' => 2, 'finished_at' => now(), 'scheduled_at' => '2026-10-17 16:30:00']);

        $props = artProps(test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $game->id])));

        expect($props['kind'])->toBe('result')
            ->and($props['data']['kind'])->toBe('result')
            ->and($props['data']['home']['name'])->toBe($game->homeEntry->team->name)
            ->and($props['data']['home']['score'])->toBe(3)
            ->and($props['data']['away']['score'])->toBe(2)
            ->and($props['data']['date'])->toBe('2026-10-17T16:30')
            ->and($props['data']['lineup'])->toBeNull()
            ->and($props['can_save'])->toBeTrue()
            ->and($props['initial']['style'])->toEqual(ArtStyle::sanitize($props['initial']['style']));

        $preview = artProps(test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $game->id, 'kind' => 'preview'])));
        expect($preview['kind'])->toBe('preview');
    });

    it('defaults to the preview for a game that has not happened', function () {
        ['game' => $game] = sheetGame();

        expect(artProps(test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $game->id])))['kind'])->toBe('preview');
    });

    it('sends the crest and the organization logo as data URIs', function () {
        ['game' => $game, 'home' => $home] = sheetGame();
        Storage::disk('s3')->put('teams/crest-128.webp', 'webp-bytes');
        $home->team->update(['logo_path' => 'teams/crest']);
        CurrentOrganization::get()->update(['logo_path' => 'brand/logo']);
        Storage::disk('s3')->put('brand/logo-128.webp', 'logo-bytes');

        $data = artProps(test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $game->id])))['data'];

        expect($data['home']['crest'])->toBe('data:image/webp;base64,'.base64_encode('webp-bytes'))
            ->and($data['org']['logo'])->toBe('data:image/webp;base64,'.base64_encode('logo-bytes'))
            ->and($data['away']['crest'])->toBeNull();
    });

    it('sends the lineup with public names only: minors abbreviated, no cards, no birth dates, no documents', function () {
        ['game' => $game, 'home' => $home] = sheetGame();
        $row = GameLineup::query()->where('game_id', $game->id)->where('entry_id', $home->id)->where('slot', 'starter')->firstOrFail();
        Person::query()->whereKey($row->person_id)->update(['name' => 'Joaquim Pereira da Silva', 'nickname' => 'Quinzinho', 'birth_date' => now()->subYears(15)->toDateString()]);

        $response = test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $game->id, 'kind' => 'lineup']));
        $props = artProps($response);
        $names = collect($props['data']['lineup']['home']['starters'])->pluck('name');

        expect($names)->toContain('Joaquim S.')
            ->and($names)->not->toContain('Quinzinho')
            ->and($props['data']['lineup']['home']['starters'])->toHaveCount(11)
            ->and($props['data']['lineup']['home']['bench'])->toHaveCount(3);

        $json = json_encode($props['data']);
        expect($json)->not->toContain('birth_date')
            ->and($json)->not->toContain('Pereira da Silva')
            ->and($json)->not->toContain('document')
            ->and($json)->not->toContain('yellow');
    });

    it('has no lineup when none was submitted', function () {
        ['game' => $game] = sheetGame();
        GameLineup::query()->where('game_id', $game->id)->delete();

        expect(artProps(test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $game->id, 'kind' => 'lineup'])))['data']['lineup'])->toBeNull();
    });

    it('sends back to the list when the game does not exist or is of another organization', function () {
        $admin = panelUser();
        test()->actingAs($admin)->get(route('panel.arts.editor', ['game' => 'nao-existe']))->assertRedirect(route('panel.arts.index'));

        $other = Organization::factory()->create();
        $foreign = CurrentOrganization::using($other, fn () => sheetGame()['game']);

        test()->actingAs($admin)->get(route('panel.arts.editor', ['game' => $foreign->id]))->assertRedirect(route('panel.arts.index'));
    });

    it('applies the preset, the template and the custom format asked for', function () {
        ['game' => $game] = sheetGame();
        $admin = panelUser();

        $preset = artProps(test()->actingAs($admin)->get(route('panel.arts.editor', ['game' => $game->id, 'preset' => 'story'])));
        expect($preset['initial']['format'])->toBe(['key' => 'story', 'width' => 1080, 'height' => 1920])
            ->and($preset['initial']['style']['layout']['variant'])->toBe('poster');

        $template = ArtTemplate::query()->create(['name' => 'Meu', 'format' => 'landscape', 'width' => 1920, 'height' => 1080, 'style' => ArtStyle::sanitize(['palette' => ['primary' => '#123456']])]);
        $applied = artProps(test()->actingAs($admin)->get(route('panel.arts.editor', ['game' => $game->id, 'template' => $template->id])));
        expect($applied['initial']['template_id'])->toBe($template->id)
            ->and($applied['initial']['style']['palette']['primary'])->toBe('#123456')
            ->and($applied['initial']['format']['key'])->toBe('landscape');

        $custom = artProps(test()->actingAs($admin)->get(route('panel.arts.editor', ['game' => $game->id, 'format' => 'custom', 'w' => 900, 'h' => 300])));
        expect($custom['initial']['format'])->toBe(['key' => 'custom', 'width' => 900, 'height' => 300]);
    });
});

describe('round and cover data', function () {
    /** Divisão de 4 equipes: rodada 1 inteira em 09/10; rodada 2 com um jogo em 09/10 e outro em 10/10. */
    function roundGames(): array
    {
        ['stage' => $stage] = fixtureDivision(4);
        app(GenerateStageFixtures::class)->handle($stage);
        $byRound = Game::query()->where('stage_id', $stage->id)->orderBy('round')->orderBy('number')->get()->groupBy('round');
        $first = $byRound[1]->values();
        $second = $byRound[2]->values();
        $first[0]->update(['scheduled_at' => '2026-10-09 18:45:00']);
        $first[1]->update(['scheduled_at' => '2026-10-09 20:45:00']);
        $second[0]->update(['scheduled_at' => '2026-10-09 21:45:00']);
        $second[1]->update(['scheduled_at' => '2026-10-10 18:45:00']);

        return [$first->all(), $second->all()];
    }

    it('lists the games of the same day by default, in time order, with team names and the date', function () {
        [, $second] = roundGames();

        $data = artProps(test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $second[0]->id, 'kind' => 'round'])))['data'];

        expect($data['kind'])->toBe('round')
            ->and($data['scope'])->toBe('day')
            ->and($data['games'])->toHaveCount(3)
            ->and(collect($data['games'])->pluck('date')->all())->toBe(['2026-10-09T18:45', '2026-10-09T20:45', '2026-10-09T21:45'])
            ->and($data['games'][0]['home']['name'])->toBeString()
            ->and($data['games'][0])->toHaveKeys(['venue', 'status', 'decided']);
    });

    it('can list the games of the same round instead', function () {
        [, $second] = roundGames();

        $data = artProps(test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $second[0]->id, 'kind' => 'round', 'scope' => 'round'])))['data'];

        expect($data['scope'])->toBe('round')
            ->and(collect($data['games'])->pluck('id')->sort()->values()->all())->toBe(collect($second)->pluck('id')->sort()->values()->all());
    });

    it('limits the number of games and never leaks other editions or organizations', function () {
        [$first] = roundGames();
        $other = Organization::factory()->create();
        CurrentOrganization::using($other, function () {
            ['stage' => $stage] = fixtureDivision(2);
            app(GenerateStageFixtures::class)->handle($stage);
            Game::query()->where('stage_id', $stage->id)->update(['scheduled_at' => '2026-10-09 17:00:00']);
        });

        $ids = collect(artProps(test()->actingAs(panelUser())->get(route('panel.arts.editor', ['game' => $first[0]->id, 'kind' => 'round'])))['data']['games'])->pluck('id');

        expect($ids->count())->toBeLessThanOrEqual(8)
            ->and(Game::query()->whereIn('id', $ids)->count())->toBe($ids->count());
    });

    it('only sends the games for the round art, and the cover needs no extra data', function () {
        [$first] = roundGames();
        $admin = panelUser();

        expect(artProps(test()->actingAs($admin)->get(route('panel.arts.editor', ['game' => $first[0]->id, 'kind' => 'preview'])))['data']['games'])->toBe([]);

        $cover = artProps(test()->actingAs($admin)->get(route('panel.arts.editor', ['game' => $first[0]->id, 'kind' => 'cover'])))['data'];
        expect($cover['kind'])->toBe('cover')
            ->and($cover['competition'])->toBeString()
            ->and($cover['division'])->toBeString()
            ->and($cover['games'])->toBe([])
            ->and($cover['lineup'])->toBeNull();
    });
});

describe('templates', function () {
    it('are saved with a sanitized style and the resolved format', function () {
        ['game' => $game] = sheetGame();
        $organizer = panelUser(Role::Organizer);

        test()->actingAs($organizer)->post(route('panel.arts.templates.store'), [
            'name' => 'Story da rodada',
            'format' => 'custom',
            'width' => 99999,
            'height' => 100,
            'style' => ['palette' => ['primary' => 'não é cor', 'accent' => '#ABCDEF'], 'layout' => ['variant' => 'poster'], 'text' => ['headline' => "Olá\nmundo"]],
            'game' => $game->id,
        ])->assertSessionHas('success');

        $template = ArtTemplate::query()->firstOrFail();
        expect($template->name)->toBe('Story da rodada')
            ->and([$template->format, $template->width, $template->height])->toBe(['custom', 4000, 200])
            ->and($template->style['palette']['primary'])->toBe(ArtStyle::defaults()['palette']['primary'])
            ->and($template->style['palette']['accent'])->toBe('#abcdef')
            ->and($template->style['layout']['variant'])->toBe('poster')
            ->and($template->style['text']['headline'])->toBe('Olá mundo')
            ->and($template->created_by)->toBe($organizer->id);
    });

    it('can be renamed, restyled and deleted', function () {
        $organizer = panelUser(Role::Organizer);
        $template = ArtTemplate::query()->create(['name' => 'Antigo', 'format' => 'square', 'width' => 1080, 'height' => 1080, 'style' => ArtStyle::defaults()]);

        test()->actingAs($organizer)->patch(route('panel.arts.templates.update', $template), ['name' => 'Novo', 'format' => 'portrait', 'style' => ['palette' => ['primary' => '#000000']]])->assertSessionHas('success');
        expect($template->fresh()->name)->toBe('Novo')
            ->and($template->fresh()->height)->toBe(1350)
            ->and($template->fresh()->style['palette']['primary'])->toBe('#000000');

        test()->actingAs($organizer)->delete(route('panel.arts.templates.destroy', $template))->assertSessionHas('success');
        expect(ArtTemplate::query()->count())->toBe(0);
    });

    it('require a name and a style', function () {
        $organizer = panelUser(Role::Organizer);

        test()->actingAs($organizer)->post(route('panel.arts.templates.store'), ['name' => '', 'format' => 'square', 'style' => []])->assertSessionHasErrors(['name']);
        test()->actingAs($organizer)->post(route('panel.arts.templates.store'), ['name' => 'X', 'format' => 'square'])->assertSessionHasErrors(['style']);
        expect(ArtTemplate::query()->count())->toBe(0);
    });

    it('can be read by everyone but only changed by who manages competitions', function () {
        $template = ArtTemplate::query()->create(['name' => 'Do time', 'format' => 'square', 'width' => 1080, 'height' => 1080, 'style' => ArtStyle::defaults()]);

        foreach ([Role::Operator, Role::Viewer] as $role) {
            $user = panelUser($role);
            test()->actingAs($user)->post(route('panel.arts.templates.store'), ['name' => 'X', 'format' => 'square', 'style' => ['a' => 1]])->assertSessionHas('error');
            test()->actingAs($user)->patch(route('panel.arts.templates.update', $template), ['name' => 'Y', 'format' => 'square', 'style' => ['a' => 1]])->assertSessionHas('error');
            test()->actingAs($user)->delete(route('panel.arts.templates.destroy', $template))->assertSessionHas('error');
        }

        expect(ArtTemplate::query()->count())->toBe(1)->and($template->fresh()->name)->toBe('Do time');

        $viewer = panelUser(Role::Viewer);
        ['game' => $game] = sheetGame();
        test()->actingAs($viewer)->get(route('panel.arts.editor', ['game' => $game->id, 'template' => $template->id]))->assertOk()->assertInertia(fn ($page) => $page->where('can_save', false));
    });

    it('never cross organizations', function () {
        $other = Organization::factory()->create();
        $foreign = CurrentOrganization::using($other, fn () => ArtTemplate::query()->create(['name' => 'De fora', 'format' => 'square', 'width' => 1080, 'height' => 1080, 'style' => ArtStyle::defaults()]));
        $organizer = panelUser(Role::Organizer);

        test()->actingAs($organizer)->patch(route('panel.arts.templates.update', $foreign), ['name' => 'Roubado', 'format' => 'square', 'style' => ['a' => 1]])->assertNotFound();
        test()->actingAs($organizer)->delete(route('panel.arts.templates.destroy', $foreign))->assertNotFound();
        expect(artProps(test()->actingAs($organizer)->get(route('panel.arts.index')))['templates'])->toBe([]);
    });
});

describe('images of the organization', function () {
    function artImage(string $name = 'fundo.png', int $size = 300): UploadedFile
    {
        return UploadedFile::fake()->image($name, $size, $size);
    }

    it('stores a re-encoded image and lists it for the editor', function () {
        $admin = panelUser(Role::Admin);

        test()->actingAs($admin)->post(route('panel.arts.assets.store'), ['file' => artImage(), 'kind' => 'background', 'name' => 'Estádio'])->assertSessionHas('success');

        $asset = ArtAsset::query()->firstOrFail();
        expect($asset->kind)->toBe('background')->and($asset->name)->toBe('Estádio');

        $props = artProps(test()->actingAs($admin)->get(route('panel.arts.index')));
        expect($props['assets'])->toHaveCount(1)->and($props['assets'][0]['id'])->toBe($asset->getKey());

        test()->actingAs($admin)->get(route('panel.arts.assets.file', $asset))->assertOk()->assertHeader('Content-Type', 'image/webp');
    });

    it('only organizers upload; any member reads', function () {
        test()->actingAs(panelUser(Role::Viewer))->post(route('panel.arts.assets.store'), ['file' => artImage(), 'kind' => 'logo'])->assertSessionHas('error');
        expect(ArtAsset::query()->count())->toBe(0);
    });

    it('refuses a file that is not an image and an unknown kind', function () {
        $admin = panelUser(Role::Admin);

        test()->actingAs($admin)->post(route('panel.arts.assets.store'), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'kind' => 'logo'])->assertSessionHasErrors('file');
        test()->actingAs($admin)->post(route('panel.arts.assets.store'), ['file' => artImage(), 'kind' => 'video'])->assertSessionHasErrors('kind');
    });

    it('stops at the limit and deletes with the file', function () {
        $admin = panelUser(Role::Admin);

        foreach (range(1, ArtAsset::LIMIT) as $i) {
            test()->actingAs($admin)->post(route('panel.arts.assets.store'), ['file' => artImage("a{$i}.png", 40), 'kind' => 'badge']);
        }
        expect(ArtAsset::query()->count())->toBe(ArtAsset::LIMIT);

        test()->actingAs($admin)->post(route('panel.arts.assets.store'), ['file' => artImage(), 'kind' => 'badge'])->assertSessionHasErrors('file');

        $asset = ArtAsset::query()->firstOrFail();
        test()->actingAs($admin)->delete(route('panel.arts.assets.destroy', $asset))->assertSessionHas('success');
        expect(ArtAsset::query()->count())->toBe(ArtAsset::LIMIT - 1);
    });

    it('keeps a chosen background and tint in the saved style', function () {
        $style = ArtStyle::sanitize(['background' => ['image' => str_repeat('A', 26), 'tint' => '#0b3b8c', 'tint_opacity' => 0.4, 'image_fit' => 'contain'], 'logo' => ['asset' => 'not-an-id', 'size' => 99]]);

        expect($style['background']['image'])->toBe(str_repeat('A', 26))
            ->and($style['background']['tint_opacity'])->toBe(0.4)
            ->and($style['logo']['asset'])->toBeNull()
            ->and($style['logo']['size'])->toBeLessThanOrEqual(30);
    });
});

<?php

use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Recurso sob demanda: só existe para quem a plataforma liberou.
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['sheet_import']]));
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://signed.test/{$path}?sig=1");
});

function uploadTo(Game $game, UploadedFile $file, Role $role = Role::Operator)
{
    return test()->actingAs(panelUser($role))->post(route('panel.games.uploads.store', $game), ['file' => $file]);
}

describe('sending', function () {
    it('stores a PDF in the private disk and registers it for the game', function () {
        $game = Game::factory()->create();
        $file = UploadedFile::fake()->create('sumula.pdf', 200, 'application/pdf');

        uploadTo($game, $file)->assertSessionHas('success');

        $upload = GameSheetUpload::query()->firstOrFail();

        expect($upload->game_id)->toBe($game->id)
            ->and($upload->kind)->toBe('pdf')
            ->and($upload->status)->toBe(SheetUploadStatus::Uploaded)
            ->and($upload->original_name)->toBe('sumula.pdf')
            ->and($upload->uploaded_by)->not->toBeNull()
            ->and(strlen($upload->sha256))->toBe(64);

        Storage::disk('s3-private')->assertExists($upload->path);
        expect($upload->path)->toStartWith("organizations/{$game->organization_id}/game-sheets/{$game->id}/");
    });

    it('accepts photos and spreadsheets and rejects other types', function () {
        $game = Game::factory()->create();

        uploadTo($game, UploadedFile::fake()->image('foto.jpg', 600, 800))->assertSessionHas('success');
        uploadTo($game, UploadedFile::fake()->createWithContent('dados.csv', "equipe;gols\nA;2\nB;1\n"))->assertSessionHas('success');
        uploadTo($game, UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))->assertSessionHas('error');

        expect(GameSheetUpload::query()->orderBy('created_at')->pluck('kind')->sort()->values()->all())->toBe(['image', 'structured']);
    });

    it('refuses the same file twice for the same game, but not for another game', function () {
        $game = Game::factory()->create();
        $other = Game::factory()->create();
        $content = 'conteúdo igual';

        uploadTo($game, UploadedFile::fake()->createWithContent('a.csv', $content))->assertSessionHas('success');
        uploadTo($game, UploadedFile::fake()->createWithContent('b.csv', $content))->assertSessionHas('error');
        uploadTo($other, UploadedFile::fake()->createWithContent('c.csv', $content))->assertSessionHas('success');

        expect(GameSheetUpload::query()->count())->toBe(2);
    });

    it('only lets those who operate games send or delete', function () {
        $game = Game::factory()->create();

        uploadTo($game, UploadedFile::fake()->create('sumula.pdf', 50, 'application/pdf'), Role::Viewer);

        expect(GameSheetUpload::query()->count())->toBe(0);
    });
});

describe('listing and opening', function () {
    it('lists the uploads for any member, newest first', function () {
        $game = Game::factory()->create();
        uploadTo($game, UploadedFile::fake()->createWithContent('um.csv', 'um'));
        uploadTo($game, UploadedFile::fake()->createWithContent('dois.csv', 'dois'));

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads', $game))->assertInertia(fn ($page) => $page
            ->component('Panel/Games/Uploads')
            ->has('uploads', 2)
            ->where('uploads.0.status', 'uploaded')
            ->where('max_mb', 15));
    });

    it('opens the file through a temporary signed link', function () {
        $game = Game::factory()->create();
        uploadTo($game, UploadedFile::fake()->create('sumula.pdf', 50, 'application/pdf'));
        $upload = GameSheetUpload::query()->firstOrFail();

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.show', $upload))->assertRedirectContains('https://signed.test/');
    });

    it('deletes the record and the stored file', function () {
        $game = Game::factory()->create();
        uploadTo($game, UploadedFile::fake()->create('sumula.pdf', 50, 'application/pdf'));
        $upload = GameSheetUpload::query()->firstOrFail();

        $this->actingAs(panelUser(Role::Operator))->delete(route('panel.games.uploads.destroy', $upload))->assertSessionHas('success');

        expect(GameSheetUpload::query()->count())->toBe(0);
        Storage::disk('s3-private')->assertMissing($upload->path);
    });
});

it('needs the sheet_import feature, which no package includes', function () {
    $game = Game::factory()->create();
    CurrentOrganization::get()->update(['plan' => Plan::Pro->value, 'features' => null, 'opt_in_features' => null]);

    $this->actingAs(panelUser(Role::Admin))->get(route('panel.games.uploads', $game))->assertForbidden();
});

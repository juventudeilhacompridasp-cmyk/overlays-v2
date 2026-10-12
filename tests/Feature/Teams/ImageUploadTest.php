<?php

use App\Models\User;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Support\Images\ImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Storage::fake('s3');
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://signed.test/{$path}?sig=1");
    $this->actingAs(User::factory()->create());
});

/** Binário de uma imagem PNG com transparência. */
function pngBinary(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, (int) imagecolorallocatealpha($image, 200, 30, 30, 0));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

it('generates webp variants that keep proportions, never upscale and keep transparency', function () {
    $variants = ImageVariants::make(pngBinary(1000, 500), ['full' => ['size' => 512, 'fit' => 'contain'], 'thumb' => ['size' => 128, 'fit' => 'contain']]);

    [$fullW, $fullH, $fullType] = getimagesizefromstring($variants['full']);
    [$thumbW, $thumbH] = getimagesizefromstring($variants['thumb']);

    expect($fullType)->toBe(IMAGETYPE_WEBP)
        ->and([$fullW, $fullH])->toBe([512, 256])
        ->and([$thumbW, $thumbH])->toBe([128, 64]);

    $small = ImageVariants::make(pngBinary(100, 80), ['full' => ['size' => 512, 'fit' => 'contain']]);
    expect(array_slice(getimagesizefromstring($small['full']), 0, 2))->toBe([100, 80]);

    $pixel = imagecreatefromstring($variants['full']);
    expect((imagecolorat($pixel, 500, 10) >> 24) & 127)->toBeGreaterThan(100); // canto transparente preservado
});

it('crops photos to a centered square', function () {
    $variants = ImageVariants::make(pngBinary(800, 400), ['full' => ['size' => 400, 'fit' => 'cover']]);

    expect(array_slice(getimagesizefromstring($variants['full']), 0, 2))->toBe([400, 400]);
});

it('rejects non-images and images with too many pixels', function () {
    expect(fn () => ImageVariants::make('isto não é uma imagem', ['a' => ['size' => 10, 'fit' => 'contain']]))->toThrow(InvalidArgumentException::class, 'imagem JPG, PNG ou WebP')
        ->and(fn () => ImageVariants::make(pngBinary(100, 100), ['a' => ['size' => 10, 'fit' => 'contain']], 5000))->toThrow(InvalidArgumentException::class, 'pixels demais');
});

it('uploads a team logo to the public disk with two webp sizes', function () {
    $team = Team::factory()->create();

    $this->post("/painel/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('escudo.png', 600, 400)])
        ->assertSessionHas('success', 'Escudo atualizado.');

    $team->refresh();
    expect($team->logo_path)->toStartWith("organizations/{$team->organization_id}/teams/{$team->id}/logo-");

    Storage::disk('s3')->assertExists("{$team->logo_path}-512.webp");
    Storage::disk('s3')->assertExists("{$team->logo_path}-128.webp");
    expect($team->logoUrl(128))->toContain("{$team->logo_path}-128.webp");
});

it('deletes the previous logo files when the logo is replaced or removed', function () {
    $team = Team::factory()->create();

    $this->post("/painel/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('a.png', 200, 200)]);
    $first = $team->fresh()->logo_path;

    $this->post("/painel/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('b.jpg', 200, 200)]);
    $second = $team->fresh()->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk('s3')->assertMissing("{$first}-512.webp");
    Storage::disk('s3')->assertExists("{$second}-512.webp");

    $this->delete("/painel/teams/{$team->id}/logo")->assertSessionHas('success', 'Escudo removido.');

    expect($team->fresh()->logo_path)->toBeNull();
    Storage::disk('s3')->assertMissing("{$second}-512.webp");
    Storage::disk('s3')->assertMissing("{$second}-128.webp");
});

it('removes the logo files when the team is deleted', function () {
    $team = Team::factory()->create();
    $this->post("/painel/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('a.png', 200, 200)]);
    $base = $team->fresh()->logo_path;

    $this->delete("/painel/teams/{$team->id}");

    Storage::disk('s3')->assertMissing("{$base}-512.webp");
});

it('validates logo uploads', function (mixed $file, string $message) {
    $team = Team::factory()->create();
    $file = $file instanceof Closure ? $file() : $file;

    $this->post("/painel/teams/{$team->id}/logo", ['logo' => $file])->assertSessionHasErrors(['logo' => $message]);
    expect($team->fresh()->logo_path)->toBeNull();
})->with([
    'sem arquivo' => [null, 'O campo escudo é obrigatório.'],
    'documento' => [fn () => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'), 'O campo escudo deve ser um arquivo do tipo: jpg, jpeg, png, webp.'],
    'svg' => [fn () => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'O campo escudo deve ser um arquivo do tipo: jpg, jpeg, png, webp.'],
    'grande demais' => [fn () => UploadedFile::fake()->image('big.png', 300, 300)->size(4000), 'O arquivo do campo escudo não pode ter mais de 3072 KB.'],
    'pequena demais' => [fn () => UploadedFile::fake()->image('tiny.png', 20, 20), 'A imagem do campo escudo é pequena demais (mínimo 64 x 64 pixels).'],
]);

it('reports an unreadable image as a validation error', function () {
    $team = Team::factory()->create();
    // Extensão e tipo de imagem, mas conteúdo corrompido: passa em mimes mas o GD não decodifica.
    $broken = UploadedFile::fake()->createWithContent('quebrado.png', "\x89PNG\r\n\x1a\n".str_repeat('x', 200));

    $this->post("/painel/teams/{$team->id}/logo", ['logo' => $broken])->assertSessionHasErrors('logo');
    expect($team->fresh()->logo_path)->toBeNull();
});

it('keeps athlete photos on the private disk and shows them with a signed url', function () {
    $person = Person::factory()->create();

    $this->post("/painel/people/{$person->id}/photo", ['photo' => UploadedFile::fake()->image('foto.jpg', 800, 600)])
        ->assertSessionHas('success', 'Foto atualizada.');

    $person->refresh();
    Storage::disk('s3-private')->assertExists("{$person->photo_path}-400.webp");
    Storage::disk('s3-private')->assertExists("{$person->photo_path}-96.webp");
    Storage::disk('s3')->assertMissing("{$person->photo_path}-400.webp");

    expect(array_slice(getimagesizefromstring(Storage::disk('s3-private')->get("{$person->photo_path}-400.webp")), 0, 2))->toBe([400, 400]);

    $this->get('/painel/people')->assertInertia(fn ($page) => $page->where('people.data.0.photo_url', "https://signed.test/{$person->photo_path}-96.webp?sig=1"));
    $this->get("/painel/people/{$person->id}/edit")->assertInertia(fn ($page) => $page->where('person.photo_url', "https://signed.test/{$person->photo_path}-400.webp?sig=1"));

    $this->delete("/painel/people/{$person->id}/photo")->assertSessionHas('success', 'Foto removida.');
    Storage::disk('s3-private')->assertMissing("{$person->photo_path}-400.webp");
    expect($person->fresh()->photo_path)->toBeNull();
});

it('puts the team logo in the team payloads and the public snapshot, never the athlete photo', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    $this->post("/painel/teams/{$home->team_id}/logo", ['logo' => UploadedFile::fake()->image('e.png', 300, 300)]);
    $this->post("/painel/people/{$hp[0]}/photo", ['photo' => UploadedFile::fake()->image('f.jpg', 300, 300)]);

    $snapshot = PublicGameSnapshot::for($game->fresh());
    $logo = Team::query()->find($home->team_id)->logoUrl(512);

    expect($snapshot['home']['logo_url'])->toBe($logo)->and($snapshot['away']['logo_url'])->toBeNull()
        ->and(json_encode($snapshot))->not->toContain('photo')->not->toContain('signed.test');

    $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page->where('home.logo', Team::query()->find($home->team_id)->logoUrl(128)));
    $this->get('/painel/teams')->assertInertia(fn ($page) => $page->where('teams.data.0.logo_url', fn ($url) => $url === null || str_contains($url, '-128.webp')));
});

it('does not touch logos of teams from other organizations', function () {
    $team = Team::factory()->create();
    CurrentOrganization::set(Organization::factory()->create(['slug' => 'outra']));
    config(['platform.default_organization_slug' => 'outra']);

    $this->post("/painel/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('a.png', 200, 200)])->assertNotFound();
});

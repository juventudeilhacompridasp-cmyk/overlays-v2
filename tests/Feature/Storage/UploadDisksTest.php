<?php

use App\Support\Images\ImageStore;
use App\Support\Storage\UploadDisks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    if (isset($this->uploadsRoot)) {
        File::deleteDirectory($this->uploadsRoot);
    }
});

it('keeps the bucket (s3) engine by default', function () {
    expect(UploadDisks::isLocal('s3'))->toBeFalse()
        ->and(UploadDisks::publicDisk('s3')['driver'])->toBe('s3')
        ->and(UploadDisks::privateDisk('s3')['driver'])->toBe('s3')
        ->and(UploadDisks::privateDisk('s3')['visibility'])->toBe('private');
});

it('stores on the server disk with STORAGE_DRIVER=local: public by the files URL, private by a signed route of its own', function () {
    config(['app.url' => 'https://exemplo.com.br']);

    $public = UploadDisks::publicDisk('local');
    $private = UploadDisks::privateDisk('local');

    expect(UploadDisks::isLocal('local'))->toBeTrue()
        ->and($public['driver'])->toBe('local')
        ->and($public['visibility'])->toBe('public')
        ->and(str_ends_with($public['root'], 'storage/app/uploads/public'))->toBeTrue()
        ->and($private['driver'])->toBe('local')
        ->and($private['visibility'])->toBe('private')
        ->and($private['serve'])->toBeTrue()
        ->and(str_ends_with($private['root'], 'storage/app/uploads/private'))->toBeTrue()
        // O disco privado NUNCA divide endereço com o público nem com o disco `local` (/storage): o Laravel recusa URLs repetidas.
        ->and($private['url'])->not->toBe($public['url'])
        ->and(parse_url($private['url'], PHP_URL_PATH))->not->toBe('/storage')
        ->and(parse_url($public['url'], PHP_URL_PATH))->toBe('/files');
});

it('saves, reads and removes an image on the local public disk and builds its URL', function () {
    $this->uploadsRoot = sys_get_temp_dir().'/uploads-'.bin2hex(random_bytes(4));
    config(['filesystems.disks.s3' => ['root' => $this->uploadsRoot, 'url' => 'https://exemplo.com.br/files'] + UploadDisks::publicDisk('local')]);
    Storage::purge('s3');

    $image = imagecreatetruecolor(300, 200);
    ob_start();
    imagepng($image);
    $file = UploadedFile::fake()->createWithContent('escudo.png', (string) ob_get_clean());

    $base = ImageStore::save('s3', 'teams', 'logo', $file, ['full' => ['size' => 128, 'fit' => 'contain']]);

    expect(ImageStore::publicUrl($base, 128))->toBe("https://exemplo.com.br/files/{$base}-128.webp")
        ->and(file_exists("{$this->uploadsRoot}/{$base}-128.webp"))->toBeTrue()
        ->and(ImageStore::publicDataUri($base, 128))->toStartWith('data:image/webp;base64,');

    ImageStore::delete('s3', $base, [128]);

    expect(file_exists("{$this->uploadsRoot}/{$base}-128.webp"))->toBeFalse();
});

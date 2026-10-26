<?php

use App\Support\Uploads\UploadScanner;
use Tests\TestCase;

uses(TestCase::class);

it('lets clean files through', function () {
    UploadScanner::check("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj", 'application/pdf');
    UploadScanner::check("\x89PNG\r\n\x1a\n".random_bytes(64), 'image/png');

    expect(true)->toBeTrue();
});

it('refuses PDFs with active content', function (string $marker) {
    UploadScanner::check("%PDF-1.4\n<< {$marker} (app.alert(1)) >>", 'application/pdf');
})->with(['/JavaScript', '/Launch', '/EmbeddedFile', '/OpenAction'])->throws(InvalidArgumentException::class, 'conteúdo ativo');

it('refuses files that only pretend to be PDFs', function () {
    UploadScanner::check('MZ not a pdf', 'application/pdf');
})->throws(InvalidArgumentException::class);

it('refuses code hidden in images', function () {
    UploadScanner::check("\xFF\xD8\xFF<?php system(\$_GET['x']); ?>", 'image/jpeg');
})->throws(InvalidArgumentException::class, 'código embutido');

it('is configurable', function () {
    config(['platform.uploads.scan.block_pdf_active' => false]);
    UploadScanner::check("%PDF-1.4 /JavaScript", 'application/pdf');

    config(['platform.uploads.scan.enabled' => false]);
    UploadScanner::check("\xFF\xD8<?php", 'image/jpeg');

    expect(UploadScanner::enabled())->toBeFalse();
});

it('ignores an unreachable ClamAV unless it must fail closed', function () {
    config(['platform.uploads.scan.clamav.host' => '127.0.0.1', 'platform.uploads.scan.clamav.port' => 1, 'platform.uploads.scan.clamav.timeout' => 1]);
    UploadScanner::check('texto', 'text/csv');

    config(['platform.uploads.scan.clamav.fail_closed' => true]);
    expect(fn () => UploadScanner::check('texto', 'text/csv'))->toThrow(InvalidArgumentException::class, 'verificar');
});

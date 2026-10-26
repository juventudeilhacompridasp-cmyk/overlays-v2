<?php

use App\Modules\Matches\Support\YouTubeLiveUrl;

it('normaliza links suportados do YouTube para o player incorporado', function (string $url, string $id) {
    expect(YouTubeLiveUrl::embed($url))->toBe("https://www.youtube-nocookie.com/embed/{$id}");
})->with([
    ['https://www.youtube.com/watch?v=abc123_XYz1', 'abc123_XYz1'],
    ['https://youtu.be/abc123_XYz1', 'abc123_XYz1'],
    ['https://www.youtube.com/live/abc123_XYz1', 'abc123_XYz1'],
]);

it('rejeita URLs de outros sites e IDs inválidos', function () {
    expect(YouTubeLiveUrl::valid('https://vimeo.com/123456'))->toBeFalse()
        ->and(YouTubeLiveUrl::valid('https://www.youtube.com/watch?v=short'))->toBeFalse()
        ->and(YouTubeLiveUrl::embed(null))->toBeNull();
});

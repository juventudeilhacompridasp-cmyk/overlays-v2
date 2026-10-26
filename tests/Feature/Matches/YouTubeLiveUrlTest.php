<?php

use App\Modules\Matches\Support\YouTubeLiveUrl;

it('normaliza links suportados do YouTube para o player incorporado', function (string $url, string $id) {
    expect(YouTubeLiveUrl::embed($url))->toBe("https://www.youtube.com/embed/{$id}?rel=0");
})->with([
    ['https://www.youtube.com/watch?v=abc123_XYz', 'abc123_XYz'],
    ['https://youtu.be/abc123_XYz', 'abc123_XYz'],
    ['https://www.youtube.com/live/abc123_XYz', 'abc123_XYz'],
]);

it('rejeita URLs de outros sites e IDs inválidos', function () {
    expect(YouTubeLiveUrl::valid('https://vimeo.com/123456'))->toBeFalse()
        ->and(YouTubeLiveUrl::valid('https://www.youtube.com/watch?v=short'))->toBeFalse()
        ->and(YouTubeLiveUrl::embed(null))->toBeNull();
});

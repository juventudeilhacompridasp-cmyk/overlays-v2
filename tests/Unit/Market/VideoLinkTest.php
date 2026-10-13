<?php

use App\Modules\Market\Support\VideoLink;

it('normalizes the accepted youtube, vimeo and instagram links', function (string $input, string $provider, string $id, string $url) {
    expect(VideoLink::parse($input))->toBe(['provider' => $provider, 'external_id' => $id, 'url' => $url]);
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'youtube mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'youtube shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'youtube embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'vimeo' => ['https://vimeo.com/123456789', 'vimeo', '123456789', 'https://vimeo.com/123456789'],
    'vimeo channel' => ['https://vimeo.com/channels/staffpicks/123456789', 'vimeo', '123456789', 'https://vimeo.com/123456789'],
    'vimeo player' => ['https://player.vimeo.com/video/123456789', 'vimeo', '123456789', 'https://vimeo.com/123456789'],
    'instagram post' => ['https://www.instagram.com/p/CxAbC12_-/?utm_source=ig', 'instagram', 'p/CxAbC12_-', 'https://www.instagram.com/p/CxAbC12_-/'],
    'instagram reel' => ['https://instagram.com/reel/CxAbC12_-/', 'instagram', 'reel/CxAbC12_-', 'https://www.instagram.com/reel/CxAbC12_-/'],
]);

it('refuses everything else', function (string $input) {
    expect(VideoLink::parse($input))->toBeNull();
})->with([
    'vazio' => [''],
    'sem protocolo' => ['youtube.com/watch?v=dQw4w9WgXcQ'],
    'http puro' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'javascript' => ['javascript:alert(1)'],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'outro site' => ['https://example.com/watch?v=dQw4w9WgXcQ'],
    'host parecido' => ['https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ'],
    'host com prefixo' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ'],
    'usuário na url' => ['https://youtube.com@evil.com/watch?v=dQw4w9WgXcQ'],
    'com porta' => ['https://www.youtube.com:8443/watch?v=dQw4w9WgXcQ'],
    'id inválido' => ['https://www.youtube.com/watch?v=curto'],
    'canal do youtube' => ['https://www.youtube.com/@canal'],
    'perfil do instagram' => ['https://www.instagram.com/fulano/'],
    'vimeo sem id' => ['https://vimeo.com/fulano'],
    'com espaço' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ <script>'],
    'arquivo' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ/../../x.mp4'],
]);

it('builds the embed address from the provider and identifier only', function () {
    expect(VideoLink::embedUrl('youtube', 'dQw4w9WgXcQ'))->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and(VideoLink::embedUrl('vimeo', '123456789'))->toBe('https://player.vimeo.com/video/123456789')
        ->and(VideoLink::embedUrl('instagram', 'p/CxAbC12_-'))->toBe('https://www.instagram.com/p/CxAbC12_-/embed')
        ->and(VideoLink::embedUrl('tiktok', 'x'))->toBeNull();
});

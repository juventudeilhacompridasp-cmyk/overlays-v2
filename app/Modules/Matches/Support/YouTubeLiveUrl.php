<?php

namespace App\Modules\Matches\Support;

use App\Modules\Market\Support\VideoLink;

/** Normaliza links de vídeos/transmissões do YouTube para um iframe seguro do portal. */
final class YouTubeLiveUrl
{
    public static function embed(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parsed = VideoLink::parse($url);

        if ($parsed === null || $parsed['provider'] !== 'youtube') {
            return null;
        }

        return VideoLink::embedUrl('youtube', $parsed['external_id']);
    }

    public static function valid(?string $url): bool
    {
        return $url === null || trim($url) === '' || self::embed($url) !== null;
    }
}

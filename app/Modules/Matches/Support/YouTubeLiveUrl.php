<?php

namespace App\Modules\Matches\Support;

/** Normaliza links de vídeos/transmissões do YouTube para um iframe seguro do portal. */
final class YouTubeLiveUrl
{
    public static function embed(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            return null;
        }

        $id = null;
        if ($host === 'youtu.be') {
            $id = explode('/', $path)[0] ?? null;
        } elseif (isset($parts['query']) && preg_match('/(?:^|&)v=([^&]+)/', (string) $parts['query'], $match) === 1) {
            $id = $match[1];
        } elseif (preg_match('#^(?:live|embed|shorts)/([^/]+)$#', $path, $match) === 1) {
            $id = $match[1];
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1
            ? "https://www.youtube.com/embed/{$id}?rel=0"
            : null;
    }

    public static function valid(?string $url): bool
    {
        return $url === null || trim($url) === '' || self::embed($url) !== null;
    }
}

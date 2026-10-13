<?php

namespace App\Modules\Market\Support;

/**
 * Link de vídeo aceito no Mercado (ADR 0008): só YouTube, Vimeo e Instagram, em endereços https conhecidos. O servidor
 * NUNCA baixa nem hospeda o vídeo: guarda o link normalizado (provedor + identificador) e o front só carrega o player
 * incorporado quando a pessoa clica. Puro: sem rede e sem banco.
 */
final class VideoLink
{
    public const LIMIT = 3;

    public const PROVIDERS = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'instagram' => 'Instagram'];

    /**
     * @return array{provider: string, external_id: string, url: string}|null null = link não aceito
     */
    public static function parse(string $input): ?array
    {
        $input = trim($input);

        if ($input === '' || strlen($input) > 300 || preg_match('/\s/', $input)) {
            return null;
        }

        $parts = parse_url($input);

        if ($parts === false || ! isset($parts['host']) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $host = preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;
        $path = '/'.trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);

        return match ($host) {
            'youtube.com' => self::youtube($path, $query),
            'youtu.be' => self::youtubeId(ltrim($path, '/')),
            'vimeo.com' => self::vimeo($path),
            'player.vimeo.com' => preg_match('#^/video/(\d{4,12})$#', $path, $m) ? self::vimeoId($m[1]) : null,
            'instagram.com' => self::instagram($path),
            default => null,
        };
    }

    /** Endereço do player incorporado (carregado só ao clicar). */
    public static function embedUrl(string $provider, string $externalId): ?string
    {
        return match ($provider) {
            'youtube' => "https://www.youtube-nocookie.com/embed/{$externalId}",
            'vimeo' => "https://player.vimeo.com/video/{$externalId}",
            'instagram' => "https://www.instagram.com/{$externalId}/embed",
            default => null,
        };
    }

    /** @param  array<string, mixed>  $query */
    private static function youtube(string $path, array $query): ?array
    {
        if ($path === '/watch') {
            return self::youtubeId(is_string($query['v'] ?? null) ? $query['v'] : '');
        }

        return preg_match('#^/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})$#', $path, $m) ? self::youtubeId($m[1]) : null;
    }

    private static function youtubeId(string $id): ?array
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? ['provider' => 'youtube', 'external_id' => $id, 'url' => "https://www.youtube.com/watch?v={$id}"] : null;
    }

    private static function vimeo(string $path): ?array
    {
        // vimeo.com/123456789 ou vimeo.com/channels/nome/123456789
        return preg_match('#^(?:/channels/[A-Za-z0-9_-]+)?/(\d{4,12})$#', $path, $m) ? self::vimeoId($m[1]) : null;
    }

    private static function vimeoId(string $id): array
    {
        return ['provider' => 'vimeo', 'external_id' => $id, 'url' => "https://vimeo.com/{$id}"];
    }

    private static function instagram(string $path): ?array
    {
        if (! preg_match('#^/(p|reel|tv)/([A-Za-z0-9_-]{5,40})$#', $path, $m)) {
            return null;
        }

        return ['provider' => 'instagram', 'external_id' => "{$m[1]}/{$m[2]}", 'url' => "https://www.instagram.com/{$m[1]}/{$m[2]}/"];
    }
}

<?php

namespace App\Support\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Guarda as variantes WebP de uma imagem em um disco e resolve suas URLs. O caminho "base" salvo no banco não tem
 * extensão nem tamanho: as variantes são `{base}-{tamanho}.webp`. Cada envio gera uma base nova (token aleatório),
 * o que também evita cache antigo no navegador/CDN.
 */
final class ImageStore
{
    /**
     * @param  array<string, array{size: int, fit: 'contain'|'cover'}>  $specs
     * @return string Caminho base gravado.
     */
    public static function save(string $disk, string $directory, string $prefix, UploadedFile $file, array $specs): string
    {
        $variants = ImageVariants::make((string) file_get_contents($file->getRealPath()), $specs);
        $base = trim($directory, '/').'/'.$prefix.'-'.bin2hex(random_bytes(5));

        foreach ($variants as $name => $webp) {
            Storage::disk($disk)->put(self::path($base, $specs[$name]['size']), $webp, ['ContentType' => 'image/webp', 'CacheControl' => 'public, max-age=31536000, immutable']);
        }

        return $base;
    }

    /** @param  list<int>  $sizes */
    public static function delete(string $disk, ?string $base, array $sizes): void
    {
        if ($base === null || $base === '') {
            return;
        }

        Storage::disk($disk)->delete(array_map(fn (int $size) => self::path($base, $size), $sizes));
    }

    /** URL pública (disco CDN). */
    public static function publicUrl(?string $base, int $size): ?string
    {
        return $base ? Storage::disk('s3')->url(self::path($base, $size)) : null;
    }

    /** URL temporária assinada (disco privado: dados pessoais). */
    public static function privateUrl(?string $base, int $size): ?string
    {
        if (! $base) {
            return null;
        }

        try {
            return Storage::disk('s3-private')->temporaryUrl(self::path($base, $size), now()->addMinutes(30));
        } catch (Throwable) {
            return null;
        }
    }

    private static function path(string $base, int $size): string
    {
        return "{$base}-{$size}.webp";
    }
}

<?php

namespace App\Support\Images;

use InvalidArgumentException;

/**
 * Gera variantes WebP de uma imagem enviada (JPEG, PNG ou WebP) com GD. Reencodar descarta os metadados
 * (EXIF, inclusive GPS) e neutraliza arquivos disfarçados; a orientação do EXIF é aplicada antes.
 *
 * Modos: `contain` mantém a proporção (escudos, com transparência) e `cover` recorta um quadrado central (fotos).
 * Nunca amplia: uma imagem pequena sai no tamanho original.
 */
final class ImageVariants
{
    public const MAX_PIXELS = 40_000_000;

    /**
     * @param  array<string, array{size: int, fit: 'contain'|'cover'}>  $specs  nome => tamanho máximo e modo
     * @return array<string, string> nome => conteúdo WebP
     */
    public static function make(string $binary, array $specs, int $maxPixels = self::MAX_PIXELS): array
    {
        $info = @getimagesizefromstring($binary);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('Envie uma imagem JPG, PNG ou WebP válida.');
        }

        if ($maxPixels < $info[0] * $info[1]) {
            throw new InvalidArgumentException('A imagem tem pixels demais. Reduza a resolução e tente de novo.');
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new InvalidArgumentException('Não foi possível ler a imagem enviada.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $source = self::orient($source, $binary);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $output = [];

        foreach ($specs as $name => $spec) {
            $output[$name] = self::render($source, $width, $height, $spec['size'], $spec['fit']);
        }

        return $output;
    }

    /** @param  'contain'|'cover'  $fit */
    private static function render(\GdImage $source, int $width, int $height, int $size, string $fit): string
    {
        if ($fit === 'cover') {
            $side = min($width, $height);
            $target = min($size, $side);
            [$srcX, $srcY, $srcW, $srcH, $dstW, $dstH] = [intdiv($width - $side, 2), intdiv($height - $side, 2), $side, $side, $target, $target];
        } else {
            $scale = min(1, $size / max($width, $height));
            [$srcX, $srcY, $srcW, $srcH, $dstW, $dstH] = [0, 0, $width, $height, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
        }

        $canvas = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);

        ob_start();
        imagewebp($canvas, null, 82);
        $webp = (string) ob_get_clean();

        return $webp;
    }

    /** Aplica a rotação indicada no EXIF (fotos de celular) para a imagem não sair deitada. */
    private static function orient(\GdImage $image, string $binary): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $angle = match (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }
}

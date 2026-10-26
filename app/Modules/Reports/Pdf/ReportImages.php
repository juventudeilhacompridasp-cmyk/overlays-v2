<?php

namespace App\Modules\Reports\Pdf;

use App\Modules\Matches\Models\Game;
use App\Support\Images\ImageStore;
use Throwable;

/**
 * Escudos das equipes do jogo como JPEG para o PDF (o PDF não lê WebP). Só o disco PÚBLICO (o escudo já aparece no
 * portal); o fundo transparente vira branco. Escudo ausente ou ilegível = sem imagem (o PDF desenha as iniciais).
 */
final class ReportImages
{
    /** @return array<string, array{data: string, width: int, height: int}> */
    public static function forGame(Game $game): array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);
        $out = [];

        foreach (['home' => $game->homeEntry, 'away' => $game->awayEntry] as $side => $entry) {
            $jpeg = self::jpeg(ImageStore::publicContents($entry?->team->logo_path, 128));

            if ($jpeg !== null) {
                $out[$side] = $jpeg;
            }
        }

        return $out;
    }

    /** @return array{data: string, width: int, height: int}|null */
    public static function jpeg(?string $bytes): ?array
    {
        if ($bytes === null || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $source = @imagecreatefromstring($bytes);

            if ($source === false) {
                return null;
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $canvas = imagecreatetruecolor($width, $height);
            imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
            imagealphablending($canvas, true);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
            ob_start();
            imagejpeg($canvas, null, 88);
            $data = (string) ob_get_clean();
            imagedestroy($source);
            imagedestroy($canvas);

            return $data === '' ? null : ['data' => $data, 'width' => $width, 'height' => $height];
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Modules\Portal\Support;

use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Support\Images\ImageStore;
use GdImage;
use Illuminate\Support\Carbon;

/**
 * Imagem de pré-visualização (Open Graph, 1200 x 630 PNG) do jogo para WhatsApp/redes. Só dados PÚBLICOS do
 * snapshot (equipes, placar, competição, data): nada de atletas, cartões ou documentos.
 */
final class GameShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const FONT = 'resources/fonts/BigShoulders-Bold.ttf';

    public static function png(Game $game): string
    {
        $snapshot = PublicGameSnapshot::for($game);
        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($image, true);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, self::color($image, '#111827'));
        imagefilledrectangle($image, 0, 0, self::WIDTH, 12, self::color($image, '#10b981'));

        $white = self::color($image, '#ffffff');
        $muted = self::color($image, '#9ca3af');

        self::text($image, 34, 600, 90, $snapshot['competition'].' · '.$snapshot['division'], $muted, 1100, true);
        self::text($image, 28, 600, 135, $snapshot['stage'], $muted, 1100, true);

        self::side($image, $game->homeEntry?->team?->logo_path, $snapshot['home']['name'] ?? 'A definir', 220, $white);
        self::side($image, $game->awayEntry?->team?->logo_path, $snapshot['away']['name'] ?? 'A definir', 980, $white);

        $started = $snapshot['status'] !== 'scheduled';
        $middle = $started ? $snapshot['home_score'].' x '.$snapshot['away_score'] : 'x';
        self::text($image, $started ? 130 : 110, 600, 370, $middle, $white, 360, true);

        $footer = $snapshot['finished'] ? 'Encerrado' : ($started ? 'Ao vivo' : self::when($snapshot['scheduled_at']));
        self::text($image, 36, 600, 460, $footer, self::color($image, '#10b981'), 360, true);

        if ($snapshot['venue']) {
            self::text($image, 26, 600, 590, $snapshot['venue'], $muted, 1100, true);
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private static function side(GdImage $image, ?string $logoPath, string $name, int $centerX, int $color): void
    {
        $logo = null;
        $contents = ImageStore::publicContents($logoPath, 512);

        if ($contents !== null) {
            $logo = @imagecreatefromstring($contents) ?: null;
        }

        if ($logo !== null) {
            $box = 240;
            $w = imagesx($logo);
            $h = imagesy($logo);
            $scale = min($box / $w, $box / $h);
            $dw = (int) round($w * $scale);
            $dh = (int) round($h * $scale);
            imagealphablending($image, true);
            imagecopyresampled($image, $logo, (int) ($centerX - $dw / 2), (int) (180 + ($box - $dh) / 2), 0, 0, $dw, $dh, $w, $h);
            imagedestroy($logo);
        }

        self::text($image, 44, $centerX, 500, $name, $color, 380, true);
    }

    private static function when(?string $iso): string
    {
        return $iso ? Carbon::parse($iso)->timezone(config('app.timezone'))->format('d/m/Y \à\s H:i') : 'Em breve';
    }

    /** Texto centralizado em $x, que encolhe até caber em $maxWidth. */
    private static function text(GdImage $image, int $size, int $x, int $y, string $text, int $color, int $maxWidth, bool $center): void
    {
        $font = base_path(self::FONT);
        $text = mb_strimwidth($text, 0, 60, '…');

        do {
            $box = imagettfbbox($size, 0, $font, $text);
            $width = $box === false ? 0 : $box[2] - $box[0];
        } while ($width > $maxWidth && --$size > 12);

        imagettftext($image, $size, 0, $center ? (int) ($x - $width / 2) : $x, $y, $color, $font, $text);
    }

    private static function color(GdImage $image, string $hex): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return (int) imagecolorallocate($image, $r, $g, $b);
    }
}

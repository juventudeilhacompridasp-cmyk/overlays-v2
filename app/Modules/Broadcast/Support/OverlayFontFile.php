<?php

namespace App\Modules\Broadcast\Support;

/** Reconhece o formato de uma fonte pelos primeiros bytes (a extensão enviada não vale). Coleções `.ttc` são recusadas. */
final class OverlayFontFile
{
    /** @return 'woff2'|'woff'|'ttf'|'otf'|null */
    public static function detect(string $bytes): ?string
    {
        return match (substr($bytes, 0, 4)) {
            'wOF2' => 'woff2',
            'wOFF' => 'woff',
            'OTTO' => 'otf',
            "\x00\x01\x00\x00", 'true' => 'ttf',
            default => null,
        };
    }
}

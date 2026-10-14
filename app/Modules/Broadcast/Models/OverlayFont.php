<?php

namespace App\Modules\Broadcast\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Fonte própria da organização, escolhida em `appearance.font` pelo ID. */
#[Unguarded]
class OverlayFont extends Model
{
    use BelongsToOrganization, HasUlids;

    /** Fontes por organização. */
    public const LIMIT = 4;

    /** Tamanho máximo do arquivo (bytes). */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** Formato => tipo de conteúdo servido ao navegador. */
    public const FORMATS = [
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    /** Família que o front registra para a fonte própria escolhida. */
    public const FAMILY = 'OverlayCustom';

    public function contentType(): string
    {
        return self::FORMATS[$this->format] ?? 'application/octet-stream';
    }
}

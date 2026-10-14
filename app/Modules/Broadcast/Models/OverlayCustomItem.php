<?php

namespace App\Modules\Broadcast\Models;

use App\Modules\Broadcast\Support\OverlayCustomElements;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Overlay livre da organização (Builder): tamanho do canvas e elementos já saneados por {@see OverlayCustomElements}.
 *
 * @property string $name
 * @property int $width
 * @property int $height
 * @property list<array<string, mixed>> $elements
 */
#[Unguarded]
class OverlayCustomItem extends Model
{
    use BelongsToOrganization, HasUlids;

    /** Overlays livres por organização. */
    public const LIMIT = 30;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['elements' => 'array', 'width' => 'integer', 'height' => 'integer'];
    }
}

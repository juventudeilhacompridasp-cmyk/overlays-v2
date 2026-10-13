<?php

namespace App\Modules\Arts\Models;

use App\Modules\Arts\Support\ArtStyle;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de arte da organização.
 *
 * @property array<string, mixed> $style
 */
#[Unguarded]
class ArtTemplate extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['style' => 'array', 'width' => 'integer', 'height' => 'integer'];
    }

    /** @return array{id: string, name: string, format: string, width: int, height: int, style: array<string, array<string, mixed>>} */
    public function toCard(): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'format' => $this->format,
            'width' => $this->width,
            'height' => $this->height,
            'style' => ArtStyle::sanitize($this->style),
        ];
    }
}

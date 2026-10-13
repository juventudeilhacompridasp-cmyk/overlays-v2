<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Modules\Tenancy\Enums\LegalType;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Versão publicada de um texto legal. Imutável: para mudar, publica-se uma nova versão.
 *
 * @property LegalType $type
 * @property int $version
 * @property string $body
 * @property Carbon $published_at
 */
#[Unguarded]
class LegalDocument extends Model
{
    use BelongsToOrganization, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Versões publicadas dos termos não podem ser alteradas; publique uma nova versão.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['type' => LegalType::class, 'published_at' => 'datetime'];
    }
}

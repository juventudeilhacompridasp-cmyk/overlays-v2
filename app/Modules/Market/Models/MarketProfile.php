<?php

namespace App\Modules\Market\Models;

use App\Models\User;
use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Support\Images\ImageStore;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Perfil de um jogador livre no Mercado da organização (ADR 0008).
 *
 * @property ProfileStatus $status
 * @property Carbon $birth_date
 * @property list<string>|null $positions
 * @property array<string, list<string>>|null $availability
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 */
#[Unguarded]
class MarketProfile extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'birth_date' => 'date',
            'positions' => 'array',
            'availability' => 'array',
            'contact_value' => 'encrypted',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function age(): int
    {
        return (int) $this->birth_date->diffInYears(now(), true);
    }

    /** URL temporária assinada da foto (disco privado); `$size` = 400 (completa) ou 96 (miniatura). */
    public function photoUrl(int $size = 96): ?string
    {
        return ImageStore::privateUrl($this->photo_path, $size);
    }
}

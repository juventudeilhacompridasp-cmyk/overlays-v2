<?php

namespace App\Modules\Teams\Models;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Support\Images\ImageStore;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
#[UseFactory(TeamFactory::class)]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** URL pública do escudo (CDN); `$size` = 512 (completo) ou 128 (miniatura). */
    public function logoUrl(int $size = 512): ?string
    {
        return ImageStore::publicUrl($this->logo_path, $size);
    }
}

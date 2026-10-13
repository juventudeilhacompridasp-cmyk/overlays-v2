<?php

namespace App\Modules\Teams\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Support\Images\ImageStore;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $birth_date
 */
#[Unguarded]
#[UseFactory(PersonFactory::class)]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $table = 'people';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /**
     * @return HasMany<RosterEntry, $this>
     */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }

    /** URL temporária assinada da foto (disco privado); `$size` = 400 (completa) ou 96 (miniatura). */
    public function photoUrl(int $size = 96, int $minutes = 30): ?string
    {
        return ImageStore::privateUrl($this->photo_path, $size, $minutes);
    }
}

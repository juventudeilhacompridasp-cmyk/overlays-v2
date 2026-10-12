<?php

namespace App\Modules\Registration\Models;

use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property RegistrationStatus $status
 * @property Carbon|null $consent_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property-read int|null $athletes_count
 * @property-read int|null $people_count
 */
#[Unguarded]
#[UseFactory(RegistrationFactory::class)]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return HasMany<RegistrationPerson, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(RegistrationPerson::class);
    }

    public function manageUrl(): string
    {
        return route('registration.manage', $this->token);
    }
}

<?php

namespace App\Modules\Registration\Models;

use App\Modules\Registration\Enums\ReviewStatus;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property RosterRole $role
 * @property ReviewStatus $review_status
 * @property Carbon|null $birth_date
 * @property Carbon|null $guardian_consent_at
 */
#[Unguarded]
class RegistrationPerson extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'registration_people';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'role' => RosterRole::class,
            'review_status' => ReviewStatus::class,
            'birth_date' => 'date',
            'guardian_consent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return HasMany<RegistrationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RegistrationDocument::class);
    }

    public function isMinor(): bool
    {
        return $this->birth_date !== null && $this->birth_date->age < 18;
    }
}

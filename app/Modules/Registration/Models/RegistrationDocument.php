<?php

namespace App\Modules\Registration\Models;

use App\Modules\Registration\Enums\DocumentType;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property DocumentType $type
 */
#[Unguarded]
class RegistrationDocument extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['type' => DocumentType::class];
    }

    /**
     * @return BelongsTo<RegistrationPerson, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(RegistrationPerson::class, 'registration_person_id');
    }
}

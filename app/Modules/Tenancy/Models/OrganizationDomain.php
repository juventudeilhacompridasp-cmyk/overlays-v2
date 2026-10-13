<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Domínio próprio de uma organização. Sem escopo de organização de propósito: é daqui que a organização é descoberta pelo host. */
#[Unguarded]
class OrganizationDomain extends Model
{
    use HasUlids;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}

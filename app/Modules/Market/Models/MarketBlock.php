<?php

namespace App\Modules\Market\Models;

use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Equipe bloqueada por um atleta do Mercado. */
#[Unguarded]
class MarketBlock extends Model
{
    use BelongsToOrganization, HasUlids;

    /** @return BelongsTo<MarketProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(MarketProfile::class, 'profile_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

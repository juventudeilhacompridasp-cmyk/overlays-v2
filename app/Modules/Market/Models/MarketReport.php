<?php

namespace App\Modules\Market\Models;

use App\Models\User;
use App\Modules\Market\Enums\ReportStatus;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Denúncia do Mercado: `reporter_kind = manager` denuncia o perfil; `athlete` denuncia a equipe (`team_id`).
 *
 * @property ReportStatus $status
 * @property Carbon|null $handled_at
 */
#[Unguarded]
class MarketReport extends Model
{
    use BelongsToOrganization, HasUlids;

    public const MANAGER = 'manager';

    public const ATHLETE = 'athlete';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['status' => ReportStatus::class, 'handled_at' => 'datetime'];
    }

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

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}

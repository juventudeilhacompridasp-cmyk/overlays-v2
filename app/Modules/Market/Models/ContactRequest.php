<?php

namespace App\Modules\Market\Models;

use App\Models\User;
use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pedido de contato de uma equipe a um perfil do Mercado.
 *
 * @property RequestStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $answered_at
 */
#[Unguarded]
class ContactRequest extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'market_contact_requests';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['status' => RequestStatus::class, 'expires_at' => 'datetime', 'answered_at' => 'datetime'];
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Pendente e ainda dentro do prazo? */
    public function isOpen(): bool
    {
        return $this->status === RequestStatus::Pending && $this->expires_at->isFuture();
    }
}

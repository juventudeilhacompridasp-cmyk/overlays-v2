<?php

namespace App\Modules\Teams\Models;

use App\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vínculo entre um usuário (papel `manager`) e uma equipe: define o que o gestor enxerga (ADR 0003). */
#[Unguarded]
class TeamManager extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'team_managers';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['notify' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

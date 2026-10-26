<?php

namespace App\Modules\Matches\Models;

use App\Modules\Matches\Enums\OfficialRole;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\GameOfficialFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property OfficialRole $role
 * @property string $game_id
 * @property string $official_id
 * @property Carbon|null $notified_at
 */
#[Unguarded]
#[UseFactory(GameOfficialFactory::class)]
class GameOfficial extends Model
{
    /** @use HasFactory<GameOfficialFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $table = 'game_officials';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'role' => OfficialRole::class,
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Official, $this>
     */
    public function official(): BelongsTo
    {
        return $this->belongsTo(Official::class);
    }
}

<?php

namespace App\Modules\Matches\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Esquema tático de uma equipe num jogo (ADR 0003): formação, instruções PRIVADAS da equipe e confirmação da escalação.
 * As instruções nunca vão ao snapshot público, ao overlay nem ao adversário.
 *
 * @property Carbon|null $confirmed_at
 */
#[Unguarded]
class GameTactic extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'game_tactics';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}

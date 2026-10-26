<?php

namespace App\Modules\Matches\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Evidência append-only de uma confirmação de súmula ou relatório. */
#[Unguarded]
class GameDocumentSignature extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'game_document_signatures';

    protected function casts(): array
    {
        return [
            'document_version' => 'integer',
            'expires_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}

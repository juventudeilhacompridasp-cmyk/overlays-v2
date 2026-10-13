<?php

namespace App\Modules\Matches\Models;

use App\Models\User;
use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Súmula enviada depois do jogo (foto/PDF ou arquivo estruturado). Dado pessoal: o arquivo fica em disco privado.
 *
 * @property SheetUploadStatus $status
 * @property array<string, mixed>|null $extracted
 * @property array<string, mixed>|null $reviewed
 * @property list<string>|null $review_changes
 * @property Carbon|null $reviewed_at
 */
#[Unguarded]
class GameSheetUpload extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['status' => SheetUploadStatus::class, 'extracted' => 'array', 'reviewed' => 'array', 'review_changes' => 'array', 'reviewed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

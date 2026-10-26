<?php

namespace App\Modules\Matches\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Modules\Market\Support\VideoLink;
use App\Support\Images\ImageStore;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class GameMedia extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'game_media';

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function embedUrl(): ?string
    {
        return VideoLink::embedUrl((string) $this->provider, (string) $this->external_id);
    }

    public function imageUrl(int $size = 1200): ?string
    {
        return $this->type === 'image' ? ImageStore::publicUrl($this->path, $size) : null;
    }
}

<?php

namespace App\Modules\Market\Models;

use App\Modules\Market\Support\VideoLink;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vídeo (link externo) de um perfil do Mercado. */
#[Unguarded]
class MarketMedia extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'market_media';

    /** @return BelongsTo<MarketProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(MarketProfile::class, 'profile_id');
    }

    /**
     * @return array{id: string, provider: string, provider_label: string, url: string, embed_url: string|null}
     */
    public function toCard(): array
    {
        return [
            'id' => $this->getKey(),
            'provider' => $this->provider,
            'provider_label' => VideoLink::PROVIDERS[$this->provider] ?? $this->provider,
            'url' => $this->url,
            'embed_url' => VideoLink::embedUrl($this->provider, $this->external_id),
        ];
    }
}

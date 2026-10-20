<?php

namespace App\Modules\Broadcast\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Support\Images\ImageStore;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Imagem enviada para elementos do Builder. Sempre reencodada em WebP e pública para o OBS. */
#[Unguarded]
class OverlayCustomAsset extends Model
{
    use BelongsToOrganization, HasUlids;

    public const LIMIT = 40;

    public const SIZE = 1920;

    public function url(): string
    {
        return (string) ImageStore::publicUrl($this->path, $this->variant_size);
    }
}

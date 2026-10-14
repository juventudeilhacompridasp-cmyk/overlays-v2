<?php

namespace App\Modules\Broadcast\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Support\Images\ImageStore;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Patrocinador que gira na tela `patrocinadores` do OBS. O logotipo é público (o overlay já é público por token). */
#[Unguarded]
class OverlaySponsor extends Model
{
    use BelongsToOrganization, HasUlids;

    /** Logotipos por organização (a rotação fica ilegível e pesada acima disso). */
    public const LIMIT = 12;

    public const SIZES = ['full' => 512, 'thumb' => 128];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function logoUrl(int $size = 512): ?string
    {
        return ImageStore::publicUrl($this->logo_path, $size);
    }
}

<?php

namespace App\Modules\Broadcast\Models;

use App\Modules\Broadcast\Support\ChampionshipStyle;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de estilo dos overlays de campeonato ("Meus modelos"): só as chaves de {@see ChampionshipStyle}.
 *
 * @property array<string, mixed> $options
 */
#[Unguarded]
class OverlayStyleModel extends Model
{
    use BelongsToOrganization, HasUlids;

    /** Modelos por organização. */
    public const LIMIT = 30;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['options' => 'array'];
    }
}

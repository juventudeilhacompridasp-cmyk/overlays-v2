<?php

namespace App\Modules\Broadcast\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Cena: foto do visual de um canal (aparência, camadas, patrocinadores e apresentação da escalação) com um nome. Nunca
 * guarda jogo no ar nem fotos de atletas habilitadas: isso é decisão de cada transmissão.
 *
 * @property array<string, mixed> $config
 */
#[Unguarded]
class OverlayScene extends Model
{
    use BelongsToOrganization, HasUlids;

    /** Cenas por organização. */
    public const LIMIT = 12;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['config' => 'array'];
    }
}

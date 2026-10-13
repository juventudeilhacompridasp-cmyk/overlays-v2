<?php

namespace App\Modules\Matches\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Registro de uma leitura pedida de súmula enviada (base dos limites de custo da leitura por IA). */
#[Unguarded]
class GameSheetRead extends Model
{
    use BelongsToOrganization, HasUlids;
}

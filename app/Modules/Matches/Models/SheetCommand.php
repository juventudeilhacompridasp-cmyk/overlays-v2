<?php

namespace App\Modules\Matches\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Lançamento da súmula já aplicado a partir da fila do aparelho do mesário (idempotência do reenvio). */
#[Unguarded]
class SheetCommand extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'sheet_commands';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}

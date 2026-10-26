<?php

namespace App\Modules\Matches\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Marca que um alerta operacional (jogo + tipo + detalhe) já foi enviado, para o mesmo problema não ser avisado de novo. */
#[Unguarded]
class OperationalAlertSent extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'operational_alerts_sent';
}

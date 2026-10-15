<?php

namespace App\Modules\Help\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Nota da central de ajuda escrita pela organização (procedimento interno). Aparece só para ela, para o público escolhido
 * (`panel` = quem usa o painel, `manager` = gestores de equipe), e passa pelo mesmo renderizador seguro dos artigos.
 *
 * @property string $id
 * @property string $audience
 * @property string $title
 * @property string $body
 */
#[Unguarded]
class HelpNote extends Model
{
    use BelongsToOrganization, HasUlids;

    /** Quantas notas cada organização pode ter. */
    public const LIMIT = 30;

    public const AUDIENCES = ['panel' => 'Equipe do painel', 'manager' => 'Gestores de equipe'];

    public const CATEGORY = 'Notas da organização';
}

<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Registro de que alguém abriu um documento pessoal. Somente de acréscimo: nunca é atualizado nem apagado individualmente
 * (só a limpeza por idade, `documents:prune-access-log`).
 *
 * @property string $actor_name
 * @property string $kind
 * @property string $label
 * @property Carbon $created_at
 */
#[Unguarded]
class DocumentAccess extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'document_access_log';

    public const UPDATED_AT = null;
}

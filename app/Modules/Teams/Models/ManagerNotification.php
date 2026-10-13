<?php

namespace App\Modules\Teams\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Registro de um aviso já enviado ao gestor: a chave (`dedupe_key`) impede o mesmo aviso de sair duas vezes. */
#[Unguarded]
class ManagerNotification extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'manager_notifications';
}

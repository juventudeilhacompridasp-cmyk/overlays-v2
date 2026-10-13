<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Permissão dada a um perfil (sempre lida pelo perfil, que já é da organização atual).
 *
 * @property string $permission
 */
#[Unguarded]
class AccessProfilePermission extends Model
{
    public $timestamps = false;
}

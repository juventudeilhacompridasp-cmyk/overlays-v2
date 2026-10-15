<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Exceção de permissão de um usuário (sempre lida pela participação, que já é da organização atual).
 *
 * @property string $permission
 * @property string $effect allow|deny
 */
#[Unguarded]
class MembershipPermission extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;
}

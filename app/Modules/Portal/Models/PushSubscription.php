<?php

namespace App\Modules\Portal\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Inscrição anônima em avisos push de um jogo ou de uma equipe. LGPD: não guarda nome, e-mail nem IP;
 * só o endereço que o navegador entregou. Some sozinha quando o serviço de push a recusa ou o jogo termina.
 */
#[Unguarded]
class PushSubscription extends Model
{
    use BelongsToOrganization, HasUlids;

    public const GAME = 'game';

    public const TEAM = 'team';

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}

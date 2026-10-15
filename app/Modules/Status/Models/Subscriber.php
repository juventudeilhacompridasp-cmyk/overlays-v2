<?php

namespace App\Modules\Status\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Inscrito nos avisos de incidente por e-mail. Tabela GLOBAL; guarda só o e-mail e o token secreto.
 *
 * @property string $email
 * @property string $token
 * @property list<string>|null $components chaves dos componentes acompanhados (nulo = todos)
 * @property Carbon|null $confirmed_at
 */
#[Unguarded]
class Subscriber extends Model
{
    use HasUlids;

    /** Dias que um pedido sem confirmação continua valendo. */
    public const PENDING_DAYS = 7;

    protected $table = 'status_subscribers';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'components' => 'array'];
    }

    /**
     * Quer o aviso de um incidente que afeta estes componentes? Sem escolha (nulo/vazio) quer tudo; incidente sem componente
     * (geral) avisa todo mundo; senão basta um componente em comum.
     *
     * @param  list<string>  $componentKeys
     */
    public function wantsAny(array $componentKeys): bool
    {
        $chosen = $this->components;

        if ($chosen === null || $chosen === [] || $componentKeys === []) {
            return true;
        }

        return array_intersect($chosen, $componentKeys) !== [];
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}

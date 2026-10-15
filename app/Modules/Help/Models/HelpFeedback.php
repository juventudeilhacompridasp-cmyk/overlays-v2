<?php

namespace App\Modules\Help\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Voto "isso ajudou?" de uma pessoa num artigo (o último vale). Sem texto livre. Não usa o escopo da organização: a
 * plataforma soma os votos de todas para saber quais artigos melhorar.
 *
 * @property int $user_id
 * @property string|null $organization_id
 * @property string $slug
 * @property bool $helpful
 */
#[Unguarded]
class HelpFeedback extends Model
{
    use HasUlids;

    protected $table = 'help_feedback';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['helpful' => 'boolean'];
    }
}

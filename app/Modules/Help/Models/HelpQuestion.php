<?php

namespace App\Modules\Help\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pergunta feita à ajuda com IA. `question` só existe quando ficou sem resposta (lacuna da documentação).
 *
 * @property bool $charged
 * @property bool $answered
 * @property string|null $question
 */
#[Unguarded]
class HelpQuestion extends Model
{
    use BelongsToOrganization, HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'help_questions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['charged' => 'boolean', 'answered' => 'boolean'];
    }
}

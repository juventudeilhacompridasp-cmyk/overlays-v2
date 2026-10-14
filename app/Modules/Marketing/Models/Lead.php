<?php

namespace App\Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Contato da landing page. Tabela GLOBAL da plataforma (sem organização).
 *
 * @property string $name
 * @property string $email
 * @property string|null $organization_name
 * @property string|null $interest
 * @property string $message
 * @property string $status
 * @property Carbon $consent_at
 * @property Carbon|null $handled_at
 */
#[Unguarded]
class Lead extends Model
{
    use HasUlids;

    public const STATUSES = ['new' => 'Novo', 'contacted' => 'Em contato', 'archived' => 'Arquivado'];

    protected $table = 'marketing_leads';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['consent_at' => 'datetime', 'handled_at' => 'datetime'];
    }
}

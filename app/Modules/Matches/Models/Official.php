<?php

namespace App\Modules\Matches\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\OfficialFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Árbitro, assistente ou mesário. Cadastro enxuto (nome, e-mail e telefone para o aviso da escala); nunca CPF ou documentos.
 *
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $active
 */
#[Unguarded]
#[UseFactory(OfficialFactory::class)]
class Official extends Model
{
    /** @use HasFactory<OfficialFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return HasMany<GameOfficial, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(GameOfficial::class);
    }
}

<?php

namespace App\Modules\Teams\Models;

use App\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico das alterações de elenco feitas pelo gestor da equipe (ADR 0003): camisa, apelido, desligamento e reforços.
 * Só registra o que mudou (sem CPF nem documentos).
 *
 * @property array<string, mixed>|null $details
 */
#[Unguarded]
class RosterChange extends Model
{
    use BelongsToOrganization, HasUlids;

    public const SHIRT = 'shirt';

    public const NICKNAME = 'nickname';

    public const RELEASED = 'released';

    public const SUPPLEMENT_REQUESTED = 'supplement_requested';

    public const SUPPLEMENT_APPROVED = 'supplement_approved';

    protected $table = 'roster_changes';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  array<string, mixed>  $details */
    public static function record(string $entryId, string $organizationId, string $action, ?string $personId, ?int $userId, array $details = []): self
    {
        return self::query()->create([
            'organization_id' => $organizationId,
            'entry_id' => $entryId,
            'person_id' => $personId,
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
        ]);
    }
}

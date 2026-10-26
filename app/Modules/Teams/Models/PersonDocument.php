<?php

namespace App\Modules\Teams\Models;

use App\Modules\Teams\Enums\PersonDocumentType;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\PersonDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Documento de uma pessoa com data de validade. O arquivo é dado pessoal: disco privado, URL assinada, nunca público.
 *
 * @property PersonDocumentType $type
 * @property Carbon $expires_on
 * @property string $path
 * @property string $person_id
 */
#[Unguarded]
#[UseFactory(PersonDocumentFactory::class)]
class PersonDocument extends Model
{
    /** @use HasFactory<PersonDocumentFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['type' => PersonDocumentType::class, 'expires_on' => 'date'];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}

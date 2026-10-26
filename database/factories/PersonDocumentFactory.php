<?php

namespace Database\Factories;

use App\Modules\Teams\Enums\PersonDocumentType;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\PersonDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonDocument>
 */
class PersonDocumentFactory extends Factory
{
    protected $model = PersonDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'organization_id' => fn (array $attributes) => Person::withoutGlobalScopes()->findOrFail($attributes['person_id'])->organization_id,
            'type' => PersonDocumentType::MedicalCertificate,
            'expires_on' => now()->addMonths(6)->toDateString(),
            'path' => 'organizations/test/people/test/documents/'.fake()->uuid().'.pdf',
            'original_name' => 'atestado.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ];
    }
}

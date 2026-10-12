<?php

namespace App\Modules\Teams\Http\Requests;

use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Support\Cpf;
use App\Modules\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Person|null $person */
        $person = $this->route('person');

        return [
            'name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date', 'after:1900-01-01', 'before:tomorrow'],
            'document' => ['nullable', 'string', 'max:20', self::documentRule($person?->getKey())],
        ];
    }

    /**
     * Valida o CPF (dígitos) e garante que não exista outra pessoa com o mesmo CPF na organização.
     */
    public static function documentRule(?string $ignoringPersonId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoringPersonId): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! Cpf::isValid((string) $value)) {
                $fail('O CPF informado não é válido.');

                return;
            }

            $duplicate = Person::query()
                ->where('organization_id', CurrentOrganization::id())
                ->where('document_hash', Cpf::hash((string) $value))
                ->when($ignoringPersonId !== null, fn ($query) => $query->whereKeyNot($ignoringPersonId))
                ->exists();

            if ($duplicate) {
                $fail('Já existe uma pessoa cadastrada com este CPF.');
            }
        };
    }

    /**
     * Dados prontos para gravar: o CPF vira hash (e some do array); CPF vazio mantém o que já existe.
     *
     * @return array<string, mixed>
     */
    public function personData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->safe()->only(['name', 'nickname', 'birth_date']);

        $document = (string) $this->input('document', '');

        if ($document !== '') {
            $data['document_hash'] = Cpf::hash($document);
        }

        return $data;
    }
}

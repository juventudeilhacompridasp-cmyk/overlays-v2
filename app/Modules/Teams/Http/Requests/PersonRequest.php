<?php

namespace App\Modules\Teams\Http\Requests;

use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Support\Cpf;
use App\Modules\Tenancy\CurrentOrganization;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'public_profile' => ['sometimes', 'boolean'],
        ];
    }

    /** O perfil público exige nascimento informado e 18 anos ou mais (menor ou idade desconhecida nunca tem perfil). */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->boolean('public_profile') || $validator->errors()->has('birth_date')) {
                return;
            }

            $birth = $this->input('birth_date');

            if (! is_string($birth) || $birth === '' || Carbon::parse($birth)->age < 18) {
                $validator->errors()->add('public_profile', 'O perfil público só é permitido para maiores de 18 anos, com a data de nascimento informada.');
            }
        }];
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

        // A data da autorização é guardada na primeira vez que ela é dada; desmarcar revoga na hora.
        if ($this->has('public_profile')) {
            $existing = $this->route('person');
            $current = $existing instanceof Person ? $existing->public_profile_at : null;
            $data['public_profile_at'] = $this->boolean('public_profile') ? ($current ?? now()) : null;
        }

        $document = (string) $this->input('document', '');

        if ($document !== '') {
            $data['document_hash'] = Cpf::hash($document);
        }

        return $data;
    }
}

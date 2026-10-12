<?php

namespace App\Modules\Teams\Http\Requests;

use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Support\Cpf;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RosterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ou `person_id` (pessoa já cadastrada) ou os dados de uma pessoa nova (`name` obrigatório).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('organization_id', CurrentOrganization::id())],
            'name' => ['required_without:person_id', 'nullable', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date', 'after:1900-01-01', 'before:tomorrow'],
            'document' => ['nullable', 'string', 'max:20', PersonRequest::documentRule()],
            'role' => ['required', Rule::enum(RosterRole::class)],
            'shirt_number' => ['nullable', 'integer', 'between:1,99'],
            'status' => ['nullable', Rule::enum(RosterStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['person_id' => 'pessoa', 'role' => 'função', 'shirt_number' => 'camisa'];
    }

    /**
     * Dados da pessoa nova (CPF como hash).
     *
     * @return array<string, mixed>
     */
    public function newPersonData(): array
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

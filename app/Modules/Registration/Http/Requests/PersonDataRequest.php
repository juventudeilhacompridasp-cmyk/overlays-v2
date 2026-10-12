<?php

namespace App\Modules\Registration\Http\Requests;

use App\Modules\Teams\Enums\RosterRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonDataRequest extends FormRequest
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
        return [
            'role' => ['required', Rule::enum(RosterRole::class)],
            'name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date', 'after:1900-01-01', 'before:tomorrow'],
            'document' => ['nullable', 'string', 'max:20'],
            'shirt_number' => ['nullable', 'integer', 'between:0,99'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_consent' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'função', 'name' => 'nome', 'nickname' => 'apelido', 'birth_date' => 'data de nascimento', 'document' => 'CPF', 'shirt_number' => 'camisa', 'guardian_name' => 'responsável legal'];
    }
}

<?php

namespace App\Modules\Registration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamDataRequest extends FormRequest
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
            'team_name' => ['required', 'string', 'max:120'],
            'team_short_name' => ['nullable', 'string', 'max:10'],
            'team_city' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['team_name' => 'nome da equipe', 'team_short_name' => 'sigla', 'team_city' => 'cidade', 'contact_name' => 'nome do responsável', 'contact_phone' => 'telefone'];
    }
}

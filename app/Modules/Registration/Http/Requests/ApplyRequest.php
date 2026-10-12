<?php

namespace App\Modules\Registration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRequest extends FormRequest
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
            'division_id' => ['required', 'string'],
            'team_name' => ['required', 'string', 'max:120'],
            'team_short_name' => ['nullable', 'string', 'max:10'],
            'team_city' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'division_id' => 'divisão', 'team_name' => 'nome da equipe', 'team_short_name' => 'sigla', 'team_city' => 'cidade',
            'contact_name' => 'nome do responsável', 'contact_email' => 'e-mail', 'contact_phone' => 'telefone', 'consent' => 'aceite do aviso de privacidade',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['consent.accepted' => 'É preciso aceitar o aviso de privacidade para continuar.'];
    }
}

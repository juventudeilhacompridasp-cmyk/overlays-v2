<?php

namespace App\Modules\Marketing\Http\Requests;

use App\Modules\Marketing\Support\LandingContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'organization_name' => ['nullable', 'string', 'max:120'],
            'interest' => ['nullable', Rule::in(array_keys(LandingContent::INTERESTS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'consent' => ['accepted'],
            // Armadilha para robôs: pessoas não veem este campo, então ele chega sempre vazio.
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'organization_name' => 'organização', 'interest' => 'assunto', 'message' => 'mensagem', 'consent' => 'aceite da política de privacidade'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['consent.accepted' => 'Para enviar, aceite a política de privacidade.', 'website.max' => 'Não foi possível enviar a mensagem.'];
    }
}

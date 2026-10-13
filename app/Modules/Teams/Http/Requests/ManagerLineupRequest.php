<?php

namespace App\Modules\Teams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Escalação e esquema tático enviados pelo gestor. A autorização (equipe do gestor) é feita no controlador. */
class ManagerLineupRequest extends FormRequest
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
            'formation' => ['required', 'string', 'max:20'],
            'starters' => ['present', 'array'],
            'starters.*' => ['string', 'distinct'],
            'bench' => ['present', 'array'],
            'bench.*' => ['string', 'distinct'],
            'staff' => ['present', 'array'],
            'staff.*' => ['string', 'distinct'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['string', 'max:20'],
            'captain' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['formation' => 'formação', 'starters' => 'titulares', 'bench' => 'reservas', 'staff' => 'comissão', 'positions' => 'posições', 'captain' => 'capitão', 'notes' => 'instruções'];
    }
}

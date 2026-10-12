<?php

namespace App\Modules\Matches\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameLineupRequest extends FormRequest
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
            'starters' => ['present', 'array'],
            'starters.*' => ['string', 'distinct'],
            'bench' => ['present', 'array'],
            'bench.*' => ['string', 'distinct'],
            'staff' => ['present', 'array'],
            'staff.*' => ['string', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['starters' => 'titulares', 'bench' => 'reservas', 'staff' => 'comissão'];
    }
}

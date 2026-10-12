<?php

namespace App\Modules\Matches\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameResultRequest extends FormRequest
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
            'home_score' => ['required', 'integer', 'between:0,99'],
            'away_score' => ['required', 'integer', 'between:0,99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['home_score' => 'gols do mandante', 'away_score' => 'gols do visitante'];
    }
}

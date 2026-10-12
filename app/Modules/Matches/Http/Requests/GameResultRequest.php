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
            'home_penalties' => ['nullable', 'integer', 'between:0,30', 'required_with:away_penalties'],
            'away_penalties' => ['nullable', 'integer', 'between:0,30', 'required_with:home_penalties'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['home_score' => 'gols do mandante', 'away_score' => 'gols do visitante', 'home_penalties' => 'pênaltis do mandante', 'away_penalties' => 'pênaltis do visitante'];
    }
}

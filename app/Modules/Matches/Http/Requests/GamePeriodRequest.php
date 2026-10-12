<?php

namespace App\Modules\Matches\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GamePeriodRequest extends FormRequest
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
            'action' => ['required', 'in:start,end'],
            'period' => ['required', 'integer', 'between:1,4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['action' => 'ação', 'period' => 'período'];
    }
}

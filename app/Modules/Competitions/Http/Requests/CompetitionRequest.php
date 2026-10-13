<?php

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Enums\Sport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompetitionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sport' => ['sometimes', Rule::enum(Sport::class)],
        ];
    }
}

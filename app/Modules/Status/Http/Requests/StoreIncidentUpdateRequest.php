<?php

namespace App\Modules\Status\Http\Requests;

use App\Modules\Status\Enums\IncidentState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'state' => ['required', Rule::enum(IncidentState::class)],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['state' => 'situação', 'body' => 'mensagem'];
    }
}

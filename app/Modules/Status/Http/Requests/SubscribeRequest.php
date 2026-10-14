<?php

namespace App\Modules\Status\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:160'],
            // Armadilha para robôs: pessoas não veem este campo.
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'e-mail'];
    }
}

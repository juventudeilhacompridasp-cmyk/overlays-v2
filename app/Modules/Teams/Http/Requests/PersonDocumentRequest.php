<?php

namespace App\Modules\Teams\Http\Requests;

use App\Modules\Teams\Enums\PersonDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonDocumentRequest extends FormRequest
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
            'type' => ['required', Rule::enum(PersonDocumentType::class)],
            'expires_on' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['type' => 'tipo do documento', 'expires_on' => 'validade', 'file' => 'arquivo'];
    }
}

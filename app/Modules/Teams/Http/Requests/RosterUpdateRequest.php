<?php

namespace App\Modules\Teams\Http\Requests;

use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RosterUpdateRequest extends FormRequest
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
            'role' => ['required', Rule::enum(RosterRole::class)],
            'shirt_number' => ['nullable', 'integer', 'between:1,99'],
            'status' => ['required', Rule::enum(RosterStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'função', 'shirt_number' => 'camisa'];
    }
}

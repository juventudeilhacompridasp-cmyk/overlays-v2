<?php

namespace App\Modules\Teams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonPhotoRequest extends FormRequest
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
        return ['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['photo' => 'foto'];
    }
}

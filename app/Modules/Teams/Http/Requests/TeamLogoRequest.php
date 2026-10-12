<?php

namespace App\Modules\Teams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamLogoRequest extends FormRequest
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
        // SVG fica de fora de propósito (pode carregar script); só imagens raster.
        return ['logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=64,min_height=64']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['logo' => 'escudo'];
    }
}

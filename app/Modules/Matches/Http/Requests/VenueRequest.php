<?php

namespace App\Modules\Matches\Http\Requests;

use App\Modules\Matches\Models\Venue;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VenueRequest extends FormRequest
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
        /** @var Venue|null $venue */
        $venue = $this->route('venue');

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('venues', 'name')->where('organization_id', CurrentOrganization::id())->ignore($venue?->getKey()),
            ],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}

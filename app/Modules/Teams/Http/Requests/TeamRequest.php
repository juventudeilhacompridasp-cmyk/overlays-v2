<?php

namespace App\Modules\Teams\Http\Requests;

use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
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
        /** @var Team|null $team */
        $team = $this->route('team');

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('teams', 'name')->where('organization_id', CurrentOrganization::id())->ignore($team?->getKey()),
            ],
            'short_name' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}

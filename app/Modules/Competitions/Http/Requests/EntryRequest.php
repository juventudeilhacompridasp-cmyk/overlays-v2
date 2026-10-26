<?php

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntryRequest extends FormRequest
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
        /** @var Entry|null $entry */
        $entry = $this->route('entry');
        /** @var Division|null $division */
        $division = $this->route('division');

        $rules = [
            'seed' => ['nullable', 'integer', 'between:1,999'],
            'status' => ['required', Rule::enum(EntryStatus::class)],
            'club_key' => ['nullable', 'string', 'max:120'],
            'sponsor_key' => ['nullable', 'string', 'max:120'],
        ];

        if ($entry === null) {
            $rules['team_id'] = [
                'required',
                Rule::exists('teams', 'id')->where('organization_id', CurrentOrganization::id()),
                Rule::unique('entries', 'team_id')->where('division_id', $division?->getKey()),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['team_id.unique' => 'Esta equipe já está inscrita nesta divisão.'];
    }
}

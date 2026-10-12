<?php

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Enums\Gender;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DivisionRequest extends FormRequest
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
        /** @var Division|null $division */
        $division = $this->route('division');
        /** @var Season|null $season */
        $season = $this->route('season') ?? $division?->season;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('divisions', 'name')->where('season_id', $season?->getKey())->ignore($division?->getKey()),
            ],
            'category' => ['nullable', 'string', 'max:50'],
            'gender' => ['required', Rule::enum(Gender::class)],
        ];
    }
}

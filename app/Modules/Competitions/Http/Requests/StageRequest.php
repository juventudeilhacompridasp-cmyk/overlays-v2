<?php

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Stage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StageRequest extends FormRequest
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
        /** @var Stage|null $stage */
        $stage = $this->route('stage');
        /** @var Division|null $division */
        $division = $this->route('division') ?? $stage?->division;

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(StageType::class)],
            'sequence' => [
                'required', 'integer', 'between:1,50',
                Rule::unique('stages', 'sequence')->where('division_id', $division?->getKey())->ignore($stage?->getKey()),
            ],
        ];
    }
}

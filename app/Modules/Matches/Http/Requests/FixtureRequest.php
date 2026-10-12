<?php

namespace App\Modules\Matches\Http\Requests;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Stage;
use Illuminate\Foundation\Http\FormRequest;

class FixtureRequest extends FormRequest
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
        /** @var Stage $stage */
        $stage = $this->route('stage');

        return [
            'double_round' => ['required', 'boolean'],
            'legs' => ['nullable', 'integer', 'in:1,2'],
            'groups' => [$stage->type === StageType::Groups ? 'required' : 'nullable', 'integer', 'between:1,16'],
        ];
    }
}

<?php

namespace App\Modules\Matches\Http\Requests;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Stage;
use Illuminate\Foundation\Http\FormRequest;

class DrawRequest extends FormRequest
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

        if ($stage->type === StageType::Knockout || $stage->type === StageType::League) {
            return [
                'legs' => [$stage->type === StageType::Knockout ? 'required' : 'nullable', 'integer', 'in:1,2'],
                'double_round' => [$stage->type === StageType::League ? 'required' : 'nullable', 'boolean'],
                'pairs' => ['required', 'array', 'min:1', 'max:1024'],
                'pairs.*' => ['array', 'size:2'],
                'pairs.*.*' => ['nullable', 'string', 'size:26'],
            ];
        }

        return [
            'double_round' => ['required', 'boolean'],
            'groups' => ['required', 'array', 'min:1', 'max:16'],
            'groups.*.name' => ['required', 'string', 'max:40'],
            'groups.*.entries' => ['required', 'array', 'min:2', 'max:64'],
            'groups.*.entries.*' => ['string', 'size:26'],
        ];
    }

    /**
     * @return list<array{0: ?string, 1: ?string}>
     */
    public function pairs(): array
    {
        return array_map(fn (array $pair) => [$pair[0] ?? null, $pair[1] ?? null], array_values($this->validated('pairs')));
    }

    /**
     * @return list<array{name: string, entries: list<string>}>
     */
    public function groupList(): array
    {
        return array_map(fn (array $group) => ['name' => $group['name'], 'entries' => array_values($group['entries'])], array_values($this->validated('groups')));
    }
}

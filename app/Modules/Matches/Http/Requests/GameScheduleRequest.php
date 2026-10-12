<?php

namespace App\Modules\Matches\Http\Requests;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameScheduleRequest extends FormRequest
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
            'scheduled_at' => ['nullable', 'date'],
            'venue_id' => ['nullable', Rule::exists('venues', 'id')->where('organization_id', CurrentOrganization::id())],
            'status' => ['required', Rule::in([
                GameStatus::Scheduled->value,
                GameStatus::Postponed->value,
                GameStatus::Suspended->value,
                GameStatus::Cancelled->value,
            ])],
        ];
    }
}

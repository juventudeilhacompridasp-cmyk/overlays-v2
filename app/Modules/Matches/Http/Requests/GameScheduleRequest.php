<?php

namespace App\Modules\Matches\Http\Requests;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Support\YouTubeLiveUrl;
use App\Modules\Tenancy\CurrentOrganization;
use Closure;
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
            'youtube_live_url' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! YouTubeLiveUrl::valid($value)) {
                    $fail('Informe um link de transmissão do YouTube válido.');
                }
            }],
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

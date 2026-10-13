<?php

namespace App\Modules\Matches\Http\Requests;

use App\Modules\Matches\Enums\GameEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameEventRequest extends FormRequest
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
            'type' => ['required', Rule::in([
                GameEventType::Goal->value, GameEventType::OwnGoal->value, GameEventType::YellowCard->value,
                GameEventType::RedCard->value, GameEventType::BlueCard->value, GameEventType::Substitution->value, GameEventType::Foul->value,
            ])],
            'entry_id' => ['required', 'string'],
            'person_id' => ['required', 'string'],
            'secondary_person_id' => ['nullable', 'required_if:type,'.GameEventType::Substitution->value, 'string'],
            'period' => ['required', 'integer', 'between:1,4'],
            'minute' => ['nullable', 'integer', 'between:0,150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo', 'entry_id' => 'equipe', 'person_id' => 'pessoa',
            'secondary_person_id' => 'quem entra', 'period' => 'tempo', 'minute' => 'minuto',
        ];
    }
}

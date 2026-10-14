<?php

namespace App\Modules\Status\Http\Requests;

use App\Modules\Status\Enums\Impact;
use App\Modules\Status\Enums\IncidentState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // `platform.admin` já barra quem não é Super Admin.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'impact' => ['required', Rule::enum(Impact::class)],
            'state' => ['required', Rule::enum(IncidentState::class)],
            'components' => ['required', 'array', 'min:1'],
            'components.*' => ['string', Rule::exists('status_components', 'id')],
            'body' => ['required', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'título', 'impact' => 'impacto', 'state' => 'situação', 'components' => 'componentes', 'body' => 'mensagem', 'starts_at' => 'início', 'ends_at' => 'fim'];
    }
}

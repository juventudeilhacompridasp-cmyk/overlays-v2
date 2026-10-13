<?php

namespace App\Modules\Tenancy\Http\Requests;

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MemberStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', Password::min(8)],
            // O gestor de equipe é convidado pela tela da equipe (precisa do vínculo com ela), não aqui.
            'role' => ['required_without:profile_id', 'nullable', Rule::enum(Role::class)->only(array_filter(Role::cases(), fn (Role $role) => $role->usesPanel()))],
            'profile_id' => ['required_without:role', 'nullable', Rule::exists('access_profiles', 'id')->where('organization_id', CurrentOrganization::id())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'role' => 'papel', 'profile_id' => 'perfil'];
    }
}

<?php

namespace App\Modules\Tenancy\Http\Requests;

use App\Models\User;
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
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'role' => 'papel'];
    }
}

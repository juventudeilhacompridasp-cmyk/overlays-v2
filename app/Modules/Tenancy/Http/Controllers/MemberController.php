<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\ManageMembers;
use App\Modules\Tenancy\Enums\Ability;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Http\Requests\MemberStoreRequest;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $members = Membership::query()->with('user:id,name,email')->get()
            ->sortBy(fn (Membership $membership) => mb_strtolower($membership->user->name))
            ->values()
            ->map(fn (Membership $membership) => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'is_me' => $membership->user_id === $request->user()?->getKey(),
            ]);

        return Inertia::render('Panel/Users/Index', [
            'members' => $members,
            'roles' => collect(Role::cases())->map(fn (Role $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'abilities' => array_map(fn (Ability $ability) => $ability->label(), $role->abilities()),
            ])->values(),
        ]);
    }

    public function store(MemberStoreRequest $request, ManageMembers $members): RedirectResponse
    {
        try {
            $members->create(
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                Role::from($request->string('role')->toString()),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Usuário criado. Repasse a senha provisória por um canal seguro.');
    }

    public function update(Request $request, Membership $membership, ManageMembers $members): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        try {
            $members->changeRole($membership, Role::from($validated['role']));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $membership->user->forgetRole();

        return back()->with('success', 'Papel atualizado.');
    }

    public function destroy(Membership $membership, ManageMembers $members): RedirectResponse
    {
        try {
            $members->remove($membership);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Acesso removido. A conta continua existindo, sem acesso ao painel.');
    }
}

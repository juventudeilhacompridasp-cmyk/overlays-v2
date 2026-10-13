<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\ManageMembers;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Ability;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\AccessProfile;
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
        $members = Membership::query()->where('role', '!=', Role::TeamManager)->with(['user:id,name,email', 'profile'])->get()
            ->sortBy(fn (Membership $membership) => mb_strtolower($membership->user->name))
            ->values()
            ->map(fn (Membership $membership) => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'profile_id' => $membership->access_profile_id,
                'access' => $membership->access_profile_id !== null ? 'profile:'.$membership->access_profile_id : 'role:'.$membership->role->value,
                'access_label' => $membership->accessLabel(),
                'is_me' => $membership->user_id === $request->user()?->getKey(),
            ]);

        return Inertia::render('Panel/Users/Index', [
            'members' => $members,
            'profiles' => AccessProfile::query()->where('is_system', false)->orderBy('name')->get(['id', 'name'])->map(fn (AccessProfile $profile) => ['id' => $profile->getKey(), 'name' => $profile->name])->values(),
            'roles' => collect(Role::cases())->filter(fn (Role $role) => $role->usesPanel())->map(fn (Role $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'abilities' => array_map(fn (Ability $ability) => $ability->label(), $role->abilities()),
            ])->values(),
        ]);
    }

    public function store(MemberStoreRequest $request, ManageMembers $members): RedirectResponse
    {
        try {
            $profile = $request->filled('profile_id') ? AccessProfile::query()->findOrFail($request->string('profile_id')->toString()) : null;

            $members->create(
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $profile !== null ? Role::Viewer : Role::from($request->string('role')->toString()),
                $profile,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Usuário criado. Repasse a senha provisória por um canal seguro.');
    }

    public function update(Request $request, Membership $membership, ManageMembers $members): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required_without:profile_id', 'nullable', Rule::enum(Role::class)->only(array_filter(Role::cases(), fn (Role $role) => $role->usesPanel()))],
            'profile_id' => ['required_without:role', 'nullable', Rule::exists('access_profiles', 'id')->where('organization_id', CurrentOrganization::id())],
        ]);

        abort_if($membership->role === Role::TeamManager, 404);

        $profile = ! empty($validated['profile_id']) ? AccessProfile::query()->findOrFail($validated['profile_id']) : null;

        try {
            $members->assignAccess($membership, $profile === null ? Role::from($validated['role']) : null, $profile);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $membership->user->forgetRole();

        return back()->with('success', $profile === null ? 'Papel atualizado.' : 'Perfil de acesso atualizado.');
    }

    public function destroy(Membership $membership, ManageMembers $members): RedirectResponse
    {
        abort_if($membership->role === Role::TeamManager, 404);

        try {
            $members->remove($membership);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Acesso removido. A conta continua existindo, sem acesso ao painel.');
    }
}

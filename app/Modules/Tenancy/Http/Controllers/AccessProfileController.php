<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\ManageAccessProfiles;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Models\AccessProfile;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Support\SystemProfiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Painel > Perfis de acesso (só quem gerencia usuários): lista, matriz de permissões, duplicar e excluir (ADR 0005). */
class AccessProfileController extends Controller
{
    public function index(): Response
    {
        SystemProfiles::ensure((string) CurrentOrganization::id());

        $counts = Membership::query()->whereNotNull('access_profile_id')->get()->countBy('access_profile_id');

        return Inertia::render('Panel/Profiles/Index', [
            'profiles' => AccessProfile::query()->with('grants')->orderByDesc('is_system')->orderBy('name')->get()->map(fn (AccessProfile $profile) => [
                'id' => $profile->getKey(),
                'name' => $profile->name,
                'description' => $profile->description,
                'is_system' => $profile->is_system,
                'members' => (int) ($counts[$profile->getKey()] ?? 0),
                'permissions' => array_map(fn (Permission $permission) => $permission->label(), $profile->permissions()),
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(AccessProfile $profile): Response|RedirectResponse
    {
        if ($profile->is_system) {
            return redirect()->route('panel.profiles.index')->with('error', 'Perfis de sistema não podem ser alterados. Duplique para personalizar.');
        }

        return $this->form($profile);
    }

    public function store(Request $request, ManageAccessProfiles $profiles): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $profiles->create($data['name'], $data['description'] ?? null, $data['permissions'] ?? []);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('panel.profiles.index')->with('success', 'Perfil criado.');
    }

    public function update(Request $request, AccessProfile $profile, ManageAccessProfiles $profiles): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $profiles->update($profile, $data['name'], $data['description'] ?? null, $data['permissions'] ?? []);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('panel.profiles.index')->with('success', 'Perfil atualizado.');
    }

    public function duplicate(AccessProfile $profile, ManageAccessProfiles $profiles): RedirectResponse
    {
        $copy = $profiles->duplicate($profile);

        return redirect()->route('panel.profiles.edit', $copy)->with('success', 'Perfil duplicado. Ajuste o que for preciso.');
    }

    public function destroy(AccessProfile $profile, ManageAccessProfiles $profiles): RedirectResponse
    {
        try {
            $profiles->delete($profile);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Perfil excluído.');
    }

    private function form(?AccessProfile $profile): Response
    {
        return Inertia::render('Panel/Profiles/Form', [
            'profile' => $profile === null ? null : [
                'id' => $profile->getKey(),
                'name' => $profile->name,
                'description' => $profile->description,
                'permissions' => array_map(fn (Permission $permission) => $permission->value, $profile->permissions()),
            ],
            'catalog' => collect(Permission::cases())->map(fn (Permission $permission) => [
                'value' => $permission->value,
                'label' => $permission->label(),
                'group' => $permission->groupLabel(),
            ])->values(),
        ]);
    }

    /**
     * @return array{name: string, description?: ?string, permissions?: list<string>}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:200'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(array_map(fn (Permission $permission) => $permission->value, Permission::cases()))],
        ], [], ['name' => 'nome', 'description' => 'descrição']);
    }
}

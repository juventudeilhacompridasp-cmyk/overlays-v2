<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Tenancy\Actions\ChangeOrganizationLogo;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Identidade da organização no portal público: nome, cor principal e frase de apresentação. */
class OrganizationController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Panel/Organization/Edit', [
            'theme' => PublicTheme::current(),
            'portal_url' => route('portal.home'),
            'white_label' => CurrentOrganization::has(Feature::WhiteLabel),
            'logo_url' => CurrentOrganization::get() ? ChangeOrganizationLogo::url(CurrentOrganization::get(), 128) : null,
        ]);
    }

    public function storeLogo(Request $request, ChangeOrganizationLogo $change): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=64,min_height=64']], [], ['logo' => 'logotipo']);

        try {
            $change->handle($this->organization(), $request->file('logo'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['logo' => $exception->getMessage()]);
        }

        return back()->with('success', 'Logotipo atualizado.');
    }

    public function destroyLogo(ChangeOrganizationLogo $change): RedirectResponse
    {
        $change->handle($this->organization(), null);

        return back()->with('success', 'Logotipo removido.');
    }

    private function organization(): Organization
    {
        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        return $organization;
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'primary' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tagline' => ['nullable', 'string', 'max:160'],
        ], [], ['name' => 'nome', 'primary' => 'cor principal', 'tagline' => 'frase de apresentação']);

        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        $settings = $organization->settings ?? [];
        $settings['theme'] = ['primary' => $validated['primary'], 'tagline' => $validated['tagline'] ?? null];

        $organization->update(['name' => $validated['name'], 'settings' => $settings]);

        return back()->with('success', 'Portal atualizado.');
    }
}

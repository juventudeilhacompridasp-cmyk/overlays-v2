<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Identidade da organização no portal público: nome, cor principal e frase de apresentação. */
class OrganizationController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Panel/Organization/Edit', ['theme' => PublicTheme::current(), 'portal_url' => route('portal.home')]);
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

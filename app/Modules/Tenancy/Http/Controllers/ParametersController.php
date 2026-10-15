<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\MesarioSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel > Parâmetros (só administrador): recursos que a própria organização desliga (só reduz o pacote) e as janelas do app do
 * mesário. O padrão é o comportamento original.
 */
class ParametersController extends Controller
{
    public function edit(): Response
    {
        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        $enabled = array_merge($organization->baseFeatureKeys(), $organization->optInFeatureKeys());

        return Inertia::render('Panel/Parameters/Edit', [
            'features' => collect(Organization::SELF_DISABLEABLE)
                ->filter(fn (string $key) => in_array($key, $enabled, true))
                ->map(fn (string $key) => ['key' => $key, 'label' => Feature::from($key)->label(), 'off' => in_array($key, $organization->disabledFeatureKeys(), true)])
                ->values(),
            'mesario' => MesarioSettings::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'disabled' => ['present', 'array'],
            'disabled.*' => ['string', Rule::in(Organization::SELF_DISABLEABLE)],
            'mesario.days_ahead' => ['required', 'integer', 'between:0,7'],
            'mesario.finished_hours' => ['required', 'integer', 'between:1,48'],
            'mesario.limit' => ['required', 'integer', 'between:10,100'],
        ], [], [
            'mesario.days_ahead' => 'dias à frente',
            'mesario.finished_hours' => 'horas de encerrados',
            'mesario.limit' => 'limite de jogos',
        ]);

        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        $settings = $organization->settings ?? [];
        // Só mexe nos recursos que a organização tem; o que a plataforma liberar depois continua ligado.
        $settings['features_disabled'] = array_values(array_unique($validated['disabled']));
        $settings['mesario'] = array_map('intval', $validated['mesario']);
        $organization->update(['settings' => $settings]);

        return back()->with('success', 'Parâmetros atualizados.');
    }
}

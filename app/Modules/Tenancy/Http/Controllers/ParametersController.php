<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\MesarioSettings;
use App\Modules\Tenancy\Support\PortalQuantities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel > Parâmetros (só administrador): recursos que a própria organização desliga (só reduz o pacote) e as janelas do app do
 * mesário, as quantidades do portal público e o interruptor da API pública. O padrão é o comportamento original.
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
            'portal' => PortalQuantities::all($organization),
            'public_api' => ($organization->settings['public_api'] ?? true) !== false,
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
            'portal.home' => ['required', 'integer', 'between:4,24'],
            'portal.widgets' => ['required', 'integer', 'between:3,20'],
            'portal.scorers' => ['required', 'integer', 'between:3,30'],
            'public_api' => ['required', 'boolean'],
        ], [], [
            'mesario.days_ahead' => 'dias à frente',
            'mesario.finished_hours' => 'horas de encerrados',
            'mesario.limit' => 'limite de jogos',
            'portal.home' => 'itens por bloco da página inicial',
            'portal.widgets' => 'jogos por lista dos widgets',
            'portal.scorers' => 'artilheiros por divisão',
        ]);

        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        $settings = $organization->settings ?? [];
        // Só mexe nos recursos que a organização tem; o que a plataforma liberar depois continua ligado.
        $settings['features_disabled'] = array_values(array_unique($validated['disabled']));
        $settings['mesario'] = array_map('intval', $validated['mesario']);
        $settings['portal_quantities'] = array_map('intval', $validated['portal']);
        $settings['public_api'] = (bool) $validated['public_api'];
        $organization->update(['settings' => $settings]);

        return back()->with('success', 'Parâmetros atualizados.');
    }
}

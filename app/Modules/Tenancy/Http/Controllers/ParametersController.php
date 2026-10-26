<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Help\Support\HelpAiSettings;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\MesarioSettings;
use App\Modules\Tenancy\Support\OperationSettings;
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
            'quick_result' => OperationSettings::quickResultAllowed($organization),
            'help_ai' => ['released' => HelpAiSettings::released(), 'enabled' => HelpAiSettings::enabled(), 'configured' => HelpAiSettings::configured()],
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
            'mesario.history' => ['nullable', 'integer', 'between:0,50'],
            'portal.home' => ['required', 'integer', 'between:4,24'],
            'portal.widgets' => ['required', 'integer', 'between:3,20'],
            'portal.scorers' => ['required', 'integer', 'between:3,30'],
            'portal.stats_top' => ['required', 'integer', 'between:3,10'],
            'portal.stats_form' => ['required', 'integer', 'between:3,10'],
            'public_api' => ['required', 'boolean'],
            'quick_result' => ['required', 'boolean'],
            'help_ai' => ['sometimes', 'boolean'],
        ], [], [
            'mesario.days_ahead' => 'dias à frente',
            'mesario.finished_hours' => 'horas de encerrados',
            'mesario.limit' => 'limite de jogos',
            'mesario.history' => 'alterações recentes',
            'portal.home' => 'itens por bloco da página inicial',
            'portal.widgets' => 'jogos por lista dos widgets',
            'portal.scorers' => 'artilheiros por divisão',
            'portal.stats_top' => 'maiores resultados nas estatísticas',
            'portal.stats_form' => 'jogos da forma recente',
        ]);

        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        $settings = $organization->settings ?? [];
        // Só mexe nos recursos que a organização tem; o que a plataforma liberar depois continua ligado.
        $settings['features_disabled'] = array_values(array_unique($validated['disabled']));
        $mesario = $validated['mesario'];
        $mesario['history'] = $mesario['history'] ?? MesarioSettings::HISTORY;
        $settings['mesario'] = array_map('intval', $mesario);
        $settings['portal_quantities'] = array_map('intval', $validated['portal']);
        $settings['public_api'] = (bool) $validated['public_api'];
        $settings['operation'] = ['quick_result' => (bool) $validated['quick_result']];

        // O interruptor das respostas com IA só muda se o recurso foi liberado pela plataforma.
        if (array_key_exists('help_ai', $validated) && HelpAiSettings::released()) {
            $settings['help_ai'] = ['enabled' => (bool) $validated['help_ai']];
        }

        $organization->update(['settings' => $settings]);

        return back()->with('success', 'Parâmetros atualizados.');
    }
}

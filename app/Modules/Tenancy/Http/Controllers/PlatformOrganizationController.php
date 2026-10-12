<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Área da plataforma (`/plataforma`, só administradores da plataforma): pacotes, recursos, limites e uso por organização. */
class PlatformOrganizationController extends Controller
{
    public function index(): Response
    {
        $organizations = Organization::query()->orderBy('name')->get()->map(function (Organization $organization) {
            $usage = OrganizationUsage::of($organization);

            return [
                'id' => $organization->getKey(),
                'name' => $organization->name,
                'slug' => $organization->slug,
                'plan_label' => $organization->plan()->label(),
                'features_count' => count($organization->featureKeys()),
                'usage' => $usage,
                'last_activity' => $usage['last_activity'] !== null ? Carbon::parse($usage['last_activity'])->format('d/m/Y H:i') : null,
            ];
        });

        return Inertia::render('Platform/Organizations/Index', [
            'organizations' => $organizations,
            'features_total' => count(Feature::cases()),
        ]);
    }

    public function edit(Organization $organization): Response
    {
        return Inertia::render('Platform/Organizations/Edit', [
            'organization' => [
                'id' => $organization->getKey(),
                'name' => $organization->name,
                'slug' => $organization->slug,
                'plan' => $organization->plan()->value,
                'custom_features' => $organization->features !== null,
                'features' => $organization->featureKeys(),
                'max_users' => $organization->limit('max_users'),
                'max_competitions' => $organization->limit('max_competitions'),
            ],
            'usage' => OrganizationUsage::of($organization),
            'plans' => array_map(fn (Plan $plan) => [
                'value' => $plan->value,
                'label' => $plan->label(),
                'features' => array_map(fn (Feature $feature) => $feature->value, $plan->features()),
            ], Plan::cases()),
            'all_features' => array_map(fn (Feature $feature) => ['value' => $feature->value, 'label' => $feature->label()], Feature::cases()),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::enum(Plan::class)],
            'custom_features' => ['required', 'boolean'],
            'features' => ['array'],
            'features.*' => [Rule::enum(Feature::class)],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_competitions' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [], ['plan' => 'pacote', 'max_users' => 'limite de usuários', 'max_competitions' => 'limite de competições']);

        $limits = array_filter([
            'max_users' => isset($validated['max_users']) ? (int) $validated['max_users'] : null,
            'max_competitions' => isset($validated['max_competitions']) ? (int) $validated['max_competitions'] : null,
        ], fn ($value) => $value !== null);

        $organization->update([
            'plan' => $validated['plan'],
            'features' => $validated['custom_features'] ? array_values(array_unique($validated['features'] ?? [])) : null,
            'limits' => $limits === [] ? null : $limits,
        ]);

        return back()->with('success', 'Pacote da organização atualizado.');
    }
}

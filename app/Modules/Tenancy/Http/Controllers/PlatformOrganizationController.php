<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\CreateOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Models\OrganizationDomain;
use App\Modules\Tenancy\Support\OrganizationResolver;
use App\Modules\Tenancy\Support\OrganizationUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

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

    public function create(): Response
    {
        return Inertia::render('Platform/Organizations/Create', [
            'plans' => array_map(fn (Plan $plan) => ['value' => $plan->value, 'label' => $plan->label()], Plan::cases()),
            'base_domain' => OrganizationResolver::baseDomain(),
        ]);
    }

    public function store(Request $request, CreateOrganization $create): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('organizations', 'slug')],
            'plan' => ['required', Rule::enum(Plan::class)],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:190'],
        ], ['slug.regex' => 'Use só letras minúsculas, números e hífens.'], ['name' => 'nome', 'slug' => 'endereço (slug)', 'plan' => 'pacote', 'admin_name' => 'nome do administrador', 'admin_email' => 'e-mail do administrador']);

        try {
            $result = $create->handle($validated['name'], $validated['slug'], Plan::from($validated['plan']), $validated['admin_name'], $validated['admin_email']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['slug' => $exception->getMessage()]);
        }

        $message = "Organização “{$result['organization']->name}” criada.";
        $message .= $result['temporary_password'] !== null
            ? " Senha provisória do administrador ({$validated['admin_email']}): {$result['temporary_password']} — repasse por canal seguro; ela não será mostrada de novo."
            : " {$validated['admin_email']} já tinha acesso à plataforma e agora também administra esta organização.";

        return to_route('platform.organizations.edit', $result['organization'])->with('success', $message);
    }

    public function storeDomain(Request $request, Organization $organization): RedirectResponse
    {
        $domain = Str::lower(trim($request->string('domain')->toString()));
        $request->merge(['domain' => $domain]);

        $request->validate([
            'domain' => ['required', 'string', 'max:190', 'regex:/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', Rule::unique('organization_domains', 'domain')],
        ], ['domain.regex' => 'Informe só o domínio, por exemplo ligadailha.com.br (sem http:// nem barras).'], ['domain' => 'domínio']);

        $base = OrganizationResolver::baseDomain();

        if ($base !== null && ($domain === $base || str_ends_with($domain, ".{$base}"))) {
            return back()->withErrors(['domain' => 'Domínios sob o domínio da plataforma são os subdomínios automáticos; cadastre um domínio próprio.']);
        }

        OrganizationDomain::query()->create(['organization_id' => $organization->getKey(), 'domain' => $domain]);

        return back()->with('success', 'Domínio cadastrado. Aponte o DNS para o servidor e inclua o domínio no proxy; ele só funciona com o recurso “Domínio próprio” ligado.');
    }

    public function destroyDomain(Organization $organization, OrganizationDomain $domain): RedirectResponse
    {
        abort_unless($domain->organization_id === $organization->getKey(), 404);
        $domain->delete();

        return back()->with('success', 'Domínio removido.');
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
                'features' => $organization->baseFeatureKeys(),
                'opt_in_features' => $organization->optInFeatureKeys(),
                'max_users' => $organization->limit('max_users'),
                'max_competitions' => $organization->limit('max_competitions'),
            ],
            'usage' => OrganizationUsage::of($organization),
            'domains' => OrganizationDomain::query()->where('organization_id', $organization->getKey())->orderBy('domain')->get(['id', 'domain']),
            'subdomain' => OrganizationResolver::baseDomain() !== null ? "{$organization->slug}.".OrganizationResolver::baseDomain() : null,
            'plans' => array_map(fn (Plan $plan) => [
                'value' => $plan->value,
                'label' => $plan->label(),
                'features' => array_map(fn (Feature $feature) => $feature->value, $plan->features()),
            ], Plan::cases()),
            'all_features' => array_map(fn (Feature $feature) => ['value' => $feature->value, 'label' => $feature->label()], Feature::included()),
            'opt_in_all' => array_map(fn (Feature $feature) => ['value' => $feature->value, 'label' => $feature->label()], Feature::optIn()),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::enum(Plan::class)],
            'custom_features' => ['required', 'boolean'],
            'features' => ['array'],
            'features.*' => [Rule::in(array_map(fn (Feature $feature) => $feature->value, Feature::included()))],
            'opt_in_features' => ['array'],
            'opt_in_features.*' => [Rule::in(array_map(fn (Feature $feature) => $feature->value, Feature::optIn()))],
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
            'opt_in_features' => array_values(array_unique($validated['opt_in_features'] ?? [])) ?: null,
            'limits' => $limits === [] ? null : $limits,
        ]);

        return back()->with('success', 'Pacote da organização atualizado.');
    }
}

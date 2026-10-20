<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Models\OrganizationDomain;

/**
 * Descobre a organização pelo host: 1) domínio próprio cadastrado (exige o recurso `custom_domain`);
 * 2) subdomínio `{slug}.{platform.base_domain}`; 3) qualquer outro host cai na organização padrão.
 * Host que parece ser da organização mas não existe é 404 (nunca cai na organização padrão).
 */
final class OrganizationResolver
{
    /** Subdomínios que nunca viram organização. */
    public const RESERVED_SLUGS = ['www', 'app', 'api', 'admin', 'painel', 'plataforma', 'mail', 'static', 'cdn', 'ws', 'reverb', 'default', 'status'];

    public static function forHost(string $host): Organization
    {
        $host = strtolower(rtrim($host, '.'));
        $custom = OrganizationDomain::query()->where('domain', $host)->with('organization')->first();

        if ($custom !== null) {
            abort_unless($custom->organization->hasFeature(Feature::CustomDomain), 404);

            return $custom->organization;
        }

        $base = self::baseDomain();

        if ($base !== null && str_ends_with($host, ".{$base}")) {
            $slug = substr($host, 0, -strlen(".{$base}"));

            // `www.{base}` é a landing page do produto (mesmo tratamento do `status`).
            if ($slug === 'www') {
                return self::defaultOrganization();
            }

            // `status.{base}` é a status page da plataforma: não é organização, mas precisa de uma organização ativa para o pipeline web.
            if ($slug === 'status') {
                return self::defaultOrganization();
            }

            $organization = $slug !== '' && ! str_contains($slug, '.') && ! in_array($slug, self::RESERVED_SLUGS, true)
                ? Organization::query()->where('slug', $slug)->first()
                : null;

            abort_if($organization === null, 404);

            return $organization;
        }

        return self::defaultOrganization();
    }

    public static function defaultOrganization(): Organization
    {
        return Organization::query()->firstOrCreate(
            ['slug' => config('platform.default_organization_slug')],
            ['name' => 'Organização padrão', 'plan' => 'pro'],
        );
    }

    public static function baseDomain(): ?string
    {
        $base = config('platform.base_domain');

        return is_string($base) && $base !== '' ? strtolower($base) : null;
    }

    /** Endereço público da organização: o domínio próprio liberado, o subdomínio ou null (organização padrão / sem domínio base). */
    public static function publicHost(Organization $organization): ?string
    {
        $custom = $organization->hasFeature(Feature::CustomDomain)
            ? OrganizationDomain::query()->where('organization_id', $organization->getKey())->orderBy('created_at')->value('domain')
            : null;

        if (is_string($custom)) {
            return $custom;
        }

        $base = self::baseDomain();

        return $base !== null && $organization->slug !== config('platform.default_organization_slug') ? "{$organization->slug}.{$base}" : null;
    }
}

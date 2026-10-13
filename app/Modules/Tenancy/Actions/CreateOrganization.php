<?php

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Onboarding feito pela plataforma: cria a organização no pacote escolhido e o primeiro administrador.
 * Se o e-mail já tem usuário, ele só ganha o papel de administrador; senão o usuário nasce com senha provisória
 * (devolvida para a plataforma repassar por canal seguro) e e-mail já verificado.
 */
final class CreateOrganization
{
    /**
     * @return array{organization: Organization, temporary_password: ?string}
     */
    public function handle(string $name, string $slug, Plan $plan, string $adminName, string $adminEmail): array
    {
        $slug = Str::lower($slug);

        if (in_array($slug, OrganizationResolver::RESERVED_SLUGS, true) || $slug === config('platform.default_organization_slug')) {
            throw new InvalidArgumentException('Esse endereço (slug) é reservado. Escolha outro.');
        }

        return DB::transaction(function () use ($name, $slug, $plan, $adminName, $adminEmail): array {
            $organization = Organization::query()->create(['name' => $name, 'slug' => $slug, 'plan' => $plan->value]);

            $user = User::query()->where('email', $adminEmail)->first();
            $password = null;

            if ($user === null) {
                $password = Str::password(14, symbols: false);
                $user = User::query()->create(['name' => $adminName, 'email' => $adminEmail, 'password' => $password]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            CurrentOrganization::using($organization, fn () => Membership::query()->create(['user_id' => $user->getKey(), 'role' => Role::Admin]));

            return ['organization' => $organization, 'temporary_password' => $password];
        });
    }
}

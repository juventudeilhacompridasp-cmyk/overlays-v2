<?php

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Isolamento entre organizações: todo modelo cuja tabela tem `organization_id` precisa do trait `BelongsToOrganization`
 * (escopo global + preenchimento automático). Modelo novo que esqueça o trait deixaria uma organização ver dados de outra.
 * As exceções são globais por desenho ou filhas de um registro que já é da organização (sempre acessadas pelo pai).
 */
it('scopes every model that has an organization_id column', function () {
    $exceptions = [
        'Tenancy\\Models\\Organization', 'Tenancy\\Models\\OrganizationDomain',   // resolvem a organização pelo endereço
        'Tenancy\\Models\\AccessProfilePermission', 'Tenancy\\Models\\MembershipPermission', // filhas de perfil/participação
        'Help\\Models\\HelpFeedback', // voto do próprio usuário
        'Marketing\\Models\\Lead', 'Status\\Models\\Check', 'Status\\Models\\Component', 'Status\\Models\\Incident',
        'Status\\Models\\IncidentUpdate', 'Status\\Models\\Subscriber', // dados globais da plataforma
    ];

    $unscoped = [];

    foreach (glob(app_path('Modules/*/Models/*.php')) ?: [] as $file) {
        $short = str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(app_path('Modules/'))));
        $class = 'App\\Modules\\'.$short;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract() || in_array($short, $exceptions, true)) {
            continue;
        }

        $table = (new $class)->getTable();

        if (Schema::hasColumn($table, 'organization_id') && ! in_array(BelongsToOrganization::class, class_uses_recursive($class), true)) {
            $unscoped[] = $class;
        }
    }

    expect($unscoped)->toBe([], 'modelos com organization_id sem o trait BelongsToOrganization: '.implode(', ', $unscoped));
});

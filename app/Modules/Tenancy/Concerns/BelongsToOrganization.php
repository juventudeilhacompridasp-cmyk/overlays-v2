<?php

namespace App\Modules\Tenancy\Concerns;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para toda tabela de domínio: filtra pela organização ativa e preenche organization_id ao criar.
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('organization_id')) && CurrentOrganization::id() !== null) {
                $model->setAttribute('organization_id', CurrentOrganization::id());
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}

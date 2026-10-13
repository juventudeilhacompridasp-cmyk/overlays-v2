<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Teams\Models\TeamManager;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Support\Facades\DB;

/** Retira o vínculo do gestor com a equipe; sem nenhum vínculo restante, o papel de gestor na organização também sai. */
final class RemoveTeamManager
{
    public function handle(TeamManager $link): void
    {
        DB::transaction(function () use ($link): void {
            $userId = $link->user_id;
            $link->delete();

            if (! TeamManager::query()->where('user_id', $userId)->exists()) {
                Membership::query()->where('user_id', $userId)->where('role', Role::TeamManager)->delete();
            }
        });
    }
}

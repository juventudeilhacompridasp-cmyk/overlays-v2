<?php

namespace App\Modules\Market\Actions;

use App\Models\User;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * O atleta apaga o perfil e o acesso nesta organização (foto incluída). O usuário só é removido se não tiver acesso em
 * nenhuma outra organização (usuários são globais).
 */
final class DeleteAthleteAccount
{
    public function __construct(private readonly ChangeMarketPhoto $photo) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $profile = MarketProfile::query()->where('user_id', $user->getKey())->first();

            if ($profile !== null) {
                $this->photo->handle($profile, null);
                $profile->delete();
            }

            // Usuários são globais: sai o acesso desta organização e, se não sobrar nenhum outro, o usuário.
            DB::table('memberships')->where('user_id', $user->getKey())->where('organization_id', CurrentOrganization::id())->delete();

            $others = DB::table('memberships')->where('user_id', $user->getKey())->exists()
                || DB::table('team_managers')->where('user_id', $user->getKey())->exists();

            if (! $others) {
                DB::table('users')->where('id', $user->getKey())->delete();
            }
        });
    }
}

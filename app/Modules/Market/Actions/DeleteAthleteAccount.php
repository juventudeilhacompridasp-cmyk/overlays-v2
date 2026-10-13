<?php

namespace App\Modules\Market\Actions;

use App\Models\User;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Tenancy\Models\Membership;
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

            Membership::query()->where('user_id', $user->getKey())->delete();

            $others = DB::table('memberships')->where('user_id', $user->getKey())->exists()
                || DB::table('team_managers')->where('user_id', $user->getKey())->exists();

            if (! $others) {
                $user->delete();
            }
        });
    }
}

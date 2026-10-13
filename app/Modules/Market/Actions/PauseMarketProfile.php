<?php

namespace App\Modules\Market\Actions;

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Models\MarketProfile;

/** O atleta tira o perfil do Mercado quando quiser (volta a publicar depois). */
final class PauseMarketProfile
{
    public function handle(MarketProfile $profile): MarketProfile
    {
        if ($profile->status === ProfileStatus::Published) {
            $profile->forceFill(['status' => ProfileStatus::Paused])->save();
        }

        return $profile;
    }
}

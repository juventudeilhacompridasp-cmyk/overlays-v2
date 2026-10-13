<?php

namespace App\Console\Commands;

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Models\MarketProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('market:expire')]
#[Description('Pausa os perfis do Mercado que passaram do prazo de revalidação (60 dias)')]
class ExpireMarketProfiles extends Command
{
    public function handle(): int
    {
        $paused = MarketProfile::query()->withoutGlobalScopes()
            ->where('status', ProfileStatus::Published->value)
            ->where('expires_at', '<', now())
            ->update(['status' => ProfileStatus::Paused->value]);

        $this->info("{$paused} perfil(is) pausado(s) por falta de revalidação.");

        return self::SUCCESS;
    }
}

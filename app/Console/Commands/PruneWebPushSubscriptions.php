<?php

namespace App\Console\Commands;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Portal\Models\PushSubscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('push:prune')]
#[Description('Apaga os pedidos de aviso de jogos que já terminaram há mais de um dia (as inscrições de equipes ficam)')]
class PruneWebPushSubscriptions extends Command
{
    public function handle(): int
    {
        $finished = DB::table('games')
            ->whereIn('status', [GameStatus::Finished->value, GameStatus::Walkover->value])
            ->where('finished_at', '<', now()->subDay())
            ->pluck('id');

        $deleted = PushSubscription::query()->withoutGlobalScopes()->where('target_type', PushSubscription::GAME)->whereIn('target_id', $finished)->delete();
        $this->info("{$deleted} pedido(s) de aviso apagado(s).");

        return self::SUCCESS;
    }
}

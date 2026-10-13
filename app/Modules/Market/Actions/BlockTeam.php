<?php

namespace App\Modules\Market\Actions;

use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketBlock;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * O atleta bloqueia uma equipe: ela deixa de ver o perfil e de pedir contato, e os pedidos pendentes ou aceitos dela são
 * recusados (o contato deixa de aparecer). Desbloquear apaga o bloqueio, sem restaurar pedidos.
 */
final class BlockTeam
{
    public function block(MarketProfile $profile, Team $team): MarketBlock
    {
        return DB::transaction(function () use ($profile, $team): MarketBlock {
            ContactRequest::query()
                ->where('profile_id', $profile->getKey())
                ->where('team_id', $team->getKey())
                ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Accepted->value])
                ->update(['status' => RequestStatus::Declined->value, 'answered_at' => now()]);

            return MarketBlock::query()->firstOrCreate(['profile_id' => $profile->getKey(), 'team_id' => $team->getKey()]);
        });
    }

    public function unblock(MarketBlock $block): void
    {
        $block->delete();
    }
}

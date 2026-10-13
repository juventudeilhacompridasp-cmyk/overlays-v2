<?php

namespace App\Modules\Market\Actions;

use App\Models\User;
use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Enums\ReportStatus;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Models\MarketReport;
use App\Modules\Tenancy\CurrentOrganization;
use InvalidArgumentException;

/**
 * Moderação do Mercado pelo organizador (`manage-market`): suspende ou reativa um perfil (o motivo aparece ao atleta),
 * resolve denúncias e ajusta a cota diária de pedidos por equipe. Nada aqui lê ou altera contato do atleta.
 */
final class ModerateMarket
{
    public function suspend(MarketProfile $profile, string $reason): MarketProfile
    {
        $profile->forceFill([
            'status' => ProfileStatus::Suspended,
            'suspended_reason' => $reason,
            'suspended_at' => now(),
        ])->save();

        return $profile;
    }

    /** Reativa como PAUSADO: o atleta decide quando publicar de novo. */
    public function restore(MarketProfile $profile): MarketProfile
    {
        if ($profile->status !== ProfileStatus::Suspended) {
            throw new InvalidArgumentException('Este perfil não está suspenso.');
        }

        $profile->forceFill(['status' => ProfileStatus::Paused, 'suspended_reason' => null, 'suspended_at' => null])->save();

        return $profile;
    }

    public function resolve(MarketReport $report, User $moderator, ReportStatus $outcome, ?string $note): MarketReport
    {
        if ($outcome === ReportStatus::Open) {
            throw new InvalidArgumentException('Escolha procedente ou arquivar.');
        }

        $report->forceFill(['status' => $outcome, 'handled_by' => $moderator->getKey(), 'handled_at' => now(), 'note' => $note])->save();

        return $report;
    }

    public function dailyRequests(int $limit): void
    {
        $organization = CurrentOrganization::get();
        $settings = $organization->settings ?? [];
        $settings['market']['daily_requests'] = max(1, min($limit, 100));

        $organization->forceFill(['settings' => $settings])->save();
    }
}

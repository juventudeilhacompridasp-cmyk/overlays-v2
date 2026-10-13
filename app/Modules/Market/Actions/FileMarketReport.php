<?php

namespace App\Modules\Market\Actions;

use App\Models\User;
use App\Modules\Market\Enums\ReportStatus;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Models\MarketReport;
use App\Modules\Teams\Models\Team;
use InvalidArgumentException;

/**
 * Denúncia ao organizador: o gestor denuncia um perfil; o atleta denuncia uma equipe que lhe pediu contato. Uma denúncia
 * aberta por denunciante × perfil (× equipe), para não inundar a moderação.
 */
final class FileMarketReport
{
    public function byManager(User $manager, MarketProfile $profile, string $reason): MarketReport
    {
        return $this->file($profile, null, $manager, MarketReport::MANAGER, $reason);
    }

    public function byAthlete(User $athlete, MarketProfile $profile, Team $team, string $reason): MarketReport
    {
        return $this->file($profile, $team, $athlete, MarketReport::ATHLETE, $reason);
    }

    private function file(MarketProfile $profile, ?Team $team, User $reporter, string $kind, string $reason): MarketReport
    {
        $open = MarketReport::query()
            ->where('profile_id', $profile->getKey())
            ->where('reported_by', $reporter->getKey())
            ->where('reporter_kind', $kind)
            ->where('team_id', $team?->getKey())
            ->where('status', ReportStatus::Open->value)
            ->exists();

        if ($open) {
            throw new InvalidArgumentException('Você já tem uma denúncia aberta sobre isto. A organização vai analisá-la.');
        }

        return MarketReport::query()->create([
            'profile_id' => $profile->getKey(),
            'team_id' => $team?->getKey(),
            'reported_by' => $reporter->getKey(),
            'reporter_kind' => $kind,
            'reason' => $reason,
            'status' => ReportStatus::Open,
        ]);
    }
}

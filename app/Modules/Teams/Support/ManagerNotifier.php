<?php

namespace App\Modules\Teams\Support;

use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Teams\Mail\ManagerNotice;
use App\Modules\Teams\Models\ManagerNotification;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamManager;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\CommunicationSettings;
use App\Modules\Tenancy\Support\OrganizationUrl;
use Illuminate\Support\Facades\Mail;

/**
 * Envia avisos por e-mail aos gestores de uma equipe (ADR 0003). Cada aviso sai uma única vez por pessoa (`$key`),
 * só para quem não desligou os avisos, e o link é calculado pelo endereço da organização (funciona fora de requisições).
 */
final class ManagerNotifier
{
    /**
     * @param  list<string>  $lines
     * @return int quantos gestores receberam
     */
    public static function send(Organization $organization, Team $team, string $kind, string $key, string $subject, string $headline, array $lines, string $path): int
    {
        $setting = match ($kind) {
            'lineup_24h', 'lineup_2h' => 'manager_lineup_reminder',
            'game_changed' => 'manager_game_changed',
            'suspensions' => 'manager_suspensions',
            'documents' => 'manager_documents',
            default => null,
        };

        // Tipo desligado pela organização: não envia e não registra (religar volta a avisar o que ainda vale).
        if ($setting !== null && ! CommunicationSettings::enabled($setting, $organization)) {
            return 0;
        }

        return CurrentOrganization::using($organization, function () use ($organization, $team, $kind, $key, $subject, $headline, $lines, $path): int {
            $sent = 0;
            $name = PublicTheme::current()['name'];

            $links = TeamManager::query()->where('team_id', $team->getKey())->where('notify', true)->with('user')->get();

            foreach ($links as $link) {
                $claim = ManagerNotification::query()->firstOrCreate(
                    ['user_id' => $link->user_id, 'dedupe_key' => $key],
                    ['organization_id' => $organization->getKey(), 'kind' => $kind],
                );

                if (! $claim->wasRecentlyCreated) {
                    continue;
                }

                Mail::to($link->user->email)->queue(new ManagerNotice($link->user->name, $subject, $headline, $lines, OrganizationUrl::to($organization, $path), $name));
                $sent++;
            }

            return $sent;
        });
    }
}

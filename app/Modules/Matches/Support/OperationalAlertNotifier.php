<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Mail\OperationalAlertDigest;
use App\Modules\Matches\Models\OperationalAlertSent;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\CommunicationSettings;
use App\Modules\Tenancy\Support\OperationalAlertSettings;
use App\Modules\Tenancy\Support\OrganizationUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda por e-mail, a quem gerencia competições, um resumo dos problemas operacionais dos jogos que estão perto
 * (`OperationalAlerts`). Cada problema (jogo + tipo + detalhe) é avisado uma única vez; só o que é novo entra no e-mail.
 * Desliga pela instalação (`OPERATIONAL_ALERTS`) ou pela organização (Comunicações > `operational_alerts`); falha de e-mail só vai ao log.
 */
final class OperationalAlertNotifier
{
    /** Dias que o registro de "já avisado" é guardado (depois o jogo já passou). */
    public const KEEP_DAYS = 7;

    /** @return int quantos e-mails foram enfileirados */
    public static function run(): int
    {
        if (! OperationalAlertSettings::installationEnabled()) {
            return 0;
        }

        $sent = 0;

        foreach (Organization::query()->get() as $organization) {
            $sent += (int) CurrentOrganization::using($organization, fn (): int => self::forOrganization($organization));
        }

        OperationalAlertSent::query()->withoutGlobalScopes()->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

        return $sent;
    }

    private static function forOrganization(Organization $organization): int
    {
        if (! CommunicationSettings::enabled('operational_alerts', $organization)) {
            return 0;
        }

        $fresh = [];
        $keys = [];

        foreach ((new OperationalAlerts)->scan() as $alert) {
            $problems = [];

            foreach ($alert['problems'] as $problem) {
                $key = mb_substr("{$alert['game_id']}:{$problem['key']}", 0, 160);

                if (OperationalAlertSent::query()->where('dedupe_key', $key)->exists()) {
                    continue;
                }

                $problems[] = $problem['text'];
                $keys[] = $key;
            }

            if ($problems !== []) {
                $fresh[] = ['label' => $alert['label'], 'at' => $alert['at'], 'problems' => $problems];
            }
        }

        if ($fresh === []) {
            return 0;
        }

        $recipients = Membership::query()->with('user')->get()
            ->filter(fn (Membership $membership) => in_array(Permission::CompetitionsManage, $membership->permissions(), true) && $membership->user?->email)
            ->map(fn (Membership $membership) => $membership->user)->unique('id')->values();

        if ($recipients->isEmpty()) {
            return 0;
        }

        $link = OrganizationUrl::to($organization, '/painel/mesario/jogos');
        $sent = 0;

        foreach ($recipients as $user) {
            try {
                Mail::to($user->email)->queue(new OperationalAlertDigest($organization->name, (string) $user->name, $fresh, $link));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Alerta operacional não enviado', ['user' => $user->getKey(), 'error' => $e->getMessage()]);
            }
        }

        // Só marca como avisado depois de tentar enviar: se ninguém recebeu, a próxima rodada tenta de novo.
        if ($sent > 0) {
            foreach ($keys as $key) {
                OperationalAlertSent::query()->firstOrCreate(['dedupe_key' => $key]);
            }
        }

        return $sent;
    }
}

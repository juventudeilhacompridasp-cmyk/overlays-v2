<?php

namespace App\Console\Commands;

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Mail\MarketNotice;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\CommunicationSettings;
use App\Modules\Tenancy\Support\OrganizationUrl;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('market:expire')]
#[Description('Mercado: expira pedidos sem resposta, pausa perfis sem revalidação (60 dias) e avisa os que vão expirar em 7 dias')]
class ExpireMarketProfiles extends Command
{
    /** Dias de antecedência do aviso "seu perfil vai expirar". */
    private const NOTICE_DAYS = 7;

    public function handle(): int
    {
        $requests = ContactRequest::query()->withoutGlobalScopes()
            ->where('status', RequestStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update(['status' => RequestStatus::Expired->value]);

        $paused = MarketProfile::query()->withoutGlobalScopes()
            ->where('status', ProfileStatus::Published->value)
            ->where('expires_at', '<', now())
            ->update(['status' => ProfileStatus::Paused->value]);

        $notified = $this->notifyExpiring();

        $this->info("{$requests} pedido(s) expirado(s), {$paused} perfil(is) pausado(s), {$notified} aviso(s) de perfil prestes a expirar.");

        return self::SUCCESS;
    }

    /** Avisa uma única vez por ciclo (zera ao renovar) quem ainda está publicado e vence em até 7 dias. */
    private function notifyExpiring(): int
    {
        $sent = 0;

        $profiles = MarketProfile::query()->withoutGlobalScopes()->with('user:id,name,email')
            ->where('status', ProfileStatus::Published->value)
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(self::NOTICE_DAYS))
            ->whereNull('expiry_notice_at')
            ->get()->groupBy('organization_id');

        foreach ($profiles as $organizationId => $group) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null || ! CommunicationSettings::enabled('market_profile_expiring', $organization)) {
                continue;
            }

            CurrentOrganization::using($organization, function () use ($group, $organization, &$sent): void {
                foreach ($group as $profile) {
                    Mail::to($profile->user->email)->queue(new MarketNotice(
                        $profile->user->name,
                        'Seu perfil no Mercado vai expirar',
                        'Seu perfil no Mercado de jogadores sai da busca em '.$profile->expires_at->format('d/m/Y').'.',
                        ['Para continuar aparecendo, abra o perfil e clique em “Renovar agora”.'],
                        OrganizationUrl::to($organization, '/atleta'),
                        'Renovar o perfil',
                    ));

                    $profile->forceFill(['expiry_notice_at' => now()])->save();
                    $sent++;
                }
            });
        }

        return $sent;
    }
}

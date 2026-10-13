<?php

namespace App\Modules\Market\Listeners;

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Registration\Events\SupplementApproved;

/**
 * Pedido de reforço aprovado: os jogadores do Mercado cujo contato aceito originou o pedido passam a "contratados" e
 * saem da busca (ADR 0008). O atleta pode voltar ao Mercado depois, se ficar sem equipe.
 */
final class MarkHiredProfiles
{
    public function handle(SupplementApproved $event): void
    {
        $requests = ContactRequest::query()
            ->where('registration_id', $event->registrationId)
            ->where('status', RequestStatus::Accepted->value)
            ->get();

        foreach ($requests as $request) {
            MarketProfile::query()
                ->whereKey($request->profile_id)
                ->whereIn('status', [ProfileStatus::Published->value, ProfileStatus::Paused->value, ProfileStatus::Draft->value])
                ->update(['status' => ProfileStatus::Signed->value]);
        }
    }
}

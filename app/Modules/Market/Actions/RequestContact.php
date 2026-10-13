<?php

namespace App\Modules\Market\Actions;

use App\Models\User;
use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Mail\MarketNotice;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketBlock;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Support\MarketSettings;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\Support\CommunicationSettings;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * O gestor pede contato a um atleta, em nome de UMA das suas equipes (ADR 0008). Regras: perfil publicado e dentro do
 * prazo; equipe não bloqueada; um pedido pendente por perfil × equipe; quem já recusou uma equipe não é pedido de novo por
 * ela; cota diária por equipe. O link do e-mail é calculado na requisição (`route()`).
 */
final class RequestContact
{
    public function handle(User $manager, MarketProfile $profile, Team $team, string $message): ContactRequest
    {
        if ($profile->status !== ProfileStatus::Published || ($profile->expires_at !== null && $profile->expires_at->isPast())) {
            throw new InvalidArgumentException('Este jogador não está mais no Mercado.');
        }

        if (MarketBlock::query()->where('profile_id', $profile->getKey())->where('team_id', $team->getKey())->exists()) {
            throw new InvalidArgumentException('Este jogador não está disponível para a sua equipe.');
        }

        $previous = ContactRequest::query()->where('profile_id', $profile->getKey())->where('team_id', $team->getKey());

        if ((clone $previous)->where('status', RequestStatus::Pending->value)->where('expires_at', '>', now())->exists()) {
            throw new InvalidArgumentException('Já existe um pedido desta equipe aguardando resposta.');
        }

        if ((clone $previous)->whereIn('status', [RequestStatus::Accepted->value, RequestStatus::Declined->value])->exists()) {
            throw new InvalidArgumentException('Este jogador já respondeu a um pedido desta equipe.');
        }

        $limit = MarketSettings::dailyRequests();
        $today = ContactRequest::query()->where('team_id', $team->getKey())->where('created_at', '>=', now()->startOfDay())->count();

        if ($today >= $limit) {
            throw new InvalidArgumentException("A equipe já enviou {$limit} pedidos hoje. Tente amanhã.");
        }

        $request = ContactRequest::query()->create([
            'profile_id' => $profile->getKey(),
            'team_id' => $team->getKey(),
            'requested_by' => $manager->getKey(),
            'message' => $message,
            'status' => RequestStatus::Pending,
            'expires_at' => now()->addDays(MarketSettings::requestDays()),
        ]);

        if (CommunicationSettings::enabled('market_contact_request')) {
            $profile->loadMissing('user');

            Mail::to($profile->user->email)->queue(new MarketNotice(
                $profile->user->name,
                "{$team->name} quer falar com você",
                "A equipe {$team->name} pediu contato pelo Mercado de jogadores.",
                ["Mensagem: {$message}", 'Seu telefone ou e-mail só é revelado se você aceitar.'],
                route('athlete.dashboard'),
                'Ver o pedido',
            ));
        }

        return $request;
    }
}

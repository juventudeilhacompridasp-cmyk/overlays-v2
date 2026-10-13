<?php

namespace App\Modules\Market\Actions;

use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Mail\MarketNotice;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Tenancy\Support\CommunicationSettings;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * O atleta aceita ou recusa um pedido de contato. Aceitar é o que libera o contato (só o canal que ele escolheu) ao
 * gestor que pediu. O e-mail ao gestor não leva o contato: ele o vê na área do gestor.
 */
final class AnswerContactRequest
{
    public function accept(ContactRequest $request): ContactRequest
    {
        return $this->answer($request, RequestStatus::Accepted);
    }

    public function decline(ContactRequest $request): ContactRequest
    {
        return $this->answer($request, RequestStatus::Declined);
    }

    private function answer(ContactRequest $request, RequestStatus $status): ContactRequest
    {
        if (! $request->isOpen()) {
            throw new InvalidArgumentException('Este pedido não está mais aguardando resposta.');
        }

        $request->forceFill(['status' => $status, 'answered_at' => now()])->save();

        if (CommunicationSettings::enabled('market_contact_answered')) {
            $request->loadMissing('profile', 'team', 'requester');
            $name = filled($request->profile->display_name) ? $request->profile->display_name : 'O jogador';
            $accepted = $status === RequestStatus::Accepted;

            Mail::to($request->requester->email)->queue(new MarketNotice(
                $request->requester->name,
                $accepted ? 'Seu pedido de contato foi aceito' : 'Seu pedido de contato foi recusado',
                $accepted ? "{$name} aceitou o pedido de {$request->team->name}." : "{$name} recusou o pedido de {$request->team->name}.",
                $accepted ? ['O contato está disponível na área do gestor, em Mercado de jogadores.'] : [],
                route('manager.market.index'),
                'Abrir o Mercado',
            ));
        }

        return $request;
    }
}

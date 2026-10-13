<?php

namespace App\Modules\Market\Actions;

use App\Models\User;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Registration\Actions\StartSupplement;
use App\Modules\Registration\Models\Registration;
use InvalidArgumentException;

/**
 * Do contato aceito ao elenco (ADR 0008): o gestor abre o pedido de reforço da inscrição da PRÓPRIA equipe e o pedido de
 * contato fica ligado a ele. Quando a organização aprova o reforço (`SupplementApproved`), o perfil vira "contratado".
 * O atleta preenche CPF e documentos pelo link pessoal do reforço, como qualquer inscrição.
 */
final class StartMarketSupplement
{
    public function __construct(private readonly StartSupplement $supplement) {}

    public function handle(User $manager, ContactRequest $request, Entry $entry): Registration
    {
        if ($request->status !== RequestStatus::Accepted) {
            throw new InvalidArgumentException('O jogador ainda não aceitou o contato.');
        }

        if ($entry->team_id !== $request->team_id) {
            throw new InvalidArgumentException('Escolha uma inscrição da equipe que fez o pedido.');
        }

        $registration = $this->supplement->handle($entry, $manager);

        $request->forceFill(['registration_id' => $registration->getKey()])->save();

        return $registration;
    }
}

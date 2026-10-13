<?php

namespace App\Modules\Market\Actions;

use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Models\ContactRequest;
use InvalidArgumentException;

/** O gestor cancela um pedido que ainda não foi respondido (ou revoga um aceito: o contato deixa de aparecer). */
final class WithdrawContactRequest
{
    public function handle(ContactRequest $request): ContactRequest
    {
        if (! in_array($request->status, [RequestStatus::Pending, RequestStatus::Accepted], true)) {
            throw new InvalidArgumentException('Este pedido já foi encerrado.');
        }

        $request->forceFill(['status' => RequestStatus::Withdrawn])->save();

        return $request;
    }
}

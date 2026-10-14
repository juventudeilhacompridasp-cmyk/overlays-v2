<?php

namespace App\Modules\Status\Actions;

use App\Modules\Status\Mail\IncidentNotice;
use App\Modules\Status\Models\Incident;
use App\Modules\Status\Models\IncidentUpdate;
use App\Modules\Status\Models\Subscriber;
use App\Modules\Status\Support\StatusLinks;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa por e-mail quem confirmou a inscrição: quando um incidente/manutenção é aberto, recebe atualização ou é encerrado.
 * Vai para a fila (não atrasa a sondagem) e uma falha de envio nunca derruba a ação que a disparou.
 */
class NotifySubscribers
{
    public function handle(Incident $incident, IncidentUpdate $update): int
    {
        if (config('platform.status.notify') !== true) {
            return 0;
        }

        $sent = 0;

        try {
            Subscriber::query()->whereNotNull('confirmed_at')->orderBy('id')->each(function (Subscriber $subscriber) use ($incident, $update, &$sent) {
                Mail::to($subscriber->email)->queue(new IncidentNotice($incident->title, $update->state->label(), $update->body, StatusLinks::page(), StatusLinks::unsubscribe($subscriber->token)));
                $sent++;
            });
        } catch (Throwable $e) {
            Log::warning('Status: falha ao avisar os inscritos.', ['erro' => class_basename($e)]);
        }

        return $sent;
    }
}

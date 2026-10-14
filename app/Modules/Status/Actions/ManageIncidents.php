<?php

namespace App\Modules\Status\Actions;

use App\Modules\Status\Enums\Impact;
use App\Modules\Status\Enums\IncidentState;
use App\Modules\Status\Models\Incident;
use App\Modules\Status\Support\StatusSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Incidentes e manutenções lançados à mão pelo Super Admin. Toda mudança limpa o cache da página pública. */
class ManageIncidents
{
    /** @param list<string> $componentIds */
    public function open(string $title, Impact $impact, IncidentState $state, array $componentIds, string $body, ?Carbon $startsAt = null, ?Carbon $endsAt = null): Incident
    {
        $maintenance = $impact === Impact::Maintenance;
        $allowed = $maintenance ? IncidentState::forMaintenance() : IncidentState::forIncident();

        if (! in_array($state, $allowed, true)) {
            $state = $maintenance ? IncidentState::Scheduled : IncidentState::Investigating;
        }

        $update = null;

        $incident = DB::transaction(function () use ($title, $impact, $state, $componentIds, $body, $startsAt, $endsAt, &$update) {
            $incident = Incident::query()->create([
                'title' => $title,
                'impact' => $impact,
                'state' => $state,
                'auto' => false,
                'starts_at' => $startsAt ?? now(),
                'ends_at' => $endsAt,
                'resolved_at' => $state->isClosed() ? now() : null,
            ]);
            $incident->components()->sync($componentIds);
            $update = $incident->updates()->create(['state' => $state, 'body' => $body, 'created_at' => now()]);
            StatusSummary::forget();

            return $incident;
        });

        // Fora da transação: o e-mail só sai com o incidente já gravado.
        if ($update !== null) {
            app(NotifySubscribers::class)->handle($incident, $update);
        }

        return $incident;
    }

    /** Novo comunicado na linha do tempo; resolver/concluir fecha o incidente. */
    public function update(Incident $incident, IncidentState $state, string $body): Incident
    {
        $allowed = $incident->isMaintenance() ? IncidentState::forMaintenance() : IncidentState::forIncident();

        if (! in_array($state, $allowed, true)) {
            $state = $incident->state;
        }

        $update = DB::transaction(function () use ($incident, $state, $body) {
            $incident->update(['state' => $state, 'resolved_at' => $state->isClosed() ? ($incident->resolved_at ?? now()) : null]);

            return $incident->updates()->create(['state' => $state, 'body' => $body, 'created_at' => now()]);
        });
        StatusSummary::forget();
        app(NotifySubscribers::class)->handle($incident, $update);

        return $incident->refresh();
    }

    public function delete(Incident $incident): void
    {
        $incident->delete();
        StatusSummary::forget();
    }
}

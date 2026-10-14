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

        return DB::transaction(function () use ($title, $impact, $state, $componentIds, $body, $startsAt, $endsAt) {
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
            $incident->updates()->create(['state' => $state, 'body' => $body, 'created_at' => now()]);
            StatusSummary::forget();

            return $incident;
        });
    }

    /** Novo comunicado na linha do tempo; resolver/concluir fecha o incidente. */
    public function update(Incident $incident, IncidentState $state, string $body): Incident
    {
        $allowed = $incident->isMaintenance() ? IncidentState::forMaintenance() : IncidentState::forIncident();

        if (! in_array($state, $allowed, true)) {
            $state = $incident->state;
        }

        DB::transaction(function () use ($incident, $state, $body) {
            $incident->update(['state' => $state, 'resolved_at' => $state->isClosed() ? ($incident->resolved_at ?? now()) : null]);
            $incident->updates()->create(['state' => $state, 'body' => $body, 'created_at' => now()]);
        });
        StatusSummary::forget();

        return $incident->refresh();
    }

    public function delete(Incident $incident): void
    {
        $incident->delete();
        StatusSummary::forget();
    }
}

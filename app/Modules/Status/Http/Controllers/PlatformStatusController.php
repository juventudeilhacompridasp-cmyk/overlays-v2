<?php

namespace App\Modules\Status\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Status\Actions\ManageIncidents;
use App\Modules\Status\Enums\Impact;
use App\Modules\Status\Enums\IncidentState;
use App\Modules\Status\Http\Requests\StoreIncidentRequest;
use App\Modules\Status\Http\Requests\StoreIncidentUpdateRequest;
use App\Modules\Status\Models\Incident;
use App\Modules\Status\Models\IncidentUpdate;
use App\Modules\Status\Support\StatusComponents;
use App\Modules\Status\Support\StatusPublisher;
use App\Modules\Status\Support\StatusSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Incidentes e manutenções da status page: só o Super Admin (`platform.admin`). */
class PlatformStatusController extends Controller
{
    public function index(): Response
    {
        $incidents = Incident::query()->with(['components', 'updates'])->orderByDesc('starts_at')->limit(40)->get();

        return Inertia::render('Platform/Status/Index', [
            'summary' => StatusSummary::build(),
            'components' => StatusComponents::ensure()->map(fn ($component) => ['id' => $component->getKey(), 'name' => $component->name])->all(),
            'incidents' => $incidents->map(fn (Incident $incident) => [
                'id' => $incident->getKey(),
                'title' => $incident->title,
                'impact' => $incident->impact->value,
                'impact_label' => $incident->impact->label(),
                'state' => $incident->state->value,
                'state_label' => $incident->state->label(),
                'auto' => $incident->auto,
                'maintenance' => $incident->isMaintenance(),
                'closed' => $incident->state->isClosed(),
                'starts_at' => $incident->starts_at->copy()->utc()->toIso8601String(),
                'ends_at' => $incident->ends_at?->copy()->utc()->toIso8601String(),
                'components' => $incident->components->pluck('name')->values()->all(),
                'updates' => $incident->updates->sortByDesc('created_at')->values()->map(fn (IncidentUpdate $update) => ['state_label' => $update->state->label(), 'body' => $update->body, 'at' => $update->created_at->copy()->utc()->toIso8601String()])->all(),
            ])->all(),
            'impacts' => array_map(fn (Impact $impact) => ['value' => $impact->value, 'label' => $impact->label()], Impact::cases()),
            'states' => [
                'incident' => array_map(fn (IncidentState $state) => ['value' => $state->value, 'label' => $state->label()], IncidentState::forIncident()),
                'maintenance' => array_map(fn (IncidentState $state) => ['value' => $state->value, 'label' => $state->label()], IncidentState::forMaintenance()),
            ],
            'public_url' => route('status.index'),
        ]);
    }

    public function store(StoreIncidentRequest $request, ManageIncidents $incidents): RedirectResponse
    {
        $data = $request->validated();
        $incidents->open(
            $data['title'],
            Impact::from($data['impact']),
            IncidentState::from($data['state']),
            array_values($data['components']),
            $data['body'],
            isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null,
            isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null,
        );
        StatusPublisher::publish(StatusSummary::build());

        return back()->with('success', 'Comunicado publicado na status page.');
    }

    public function update(StoreIncidentUpdateRequest $request, Incident $incident, ManageIncidents $incidents): RedirectResponse
    {
        $incidents->update($incident, IncidentState::from($request->validated('state')), $request->validated('body'));
        StatusPublisher::publish(StatusSummary::build());

        return back()->with('success', 'Atualização publicada.');
    }

    public function destroy(Incident $incident, ManageIncidents $incidents): RedirectResponse
    {
        $incidents->delete($incident);
        StatusPublisher::publish(StatusSummary::build());

        return back()->with('success', 'Comunicado removido.');
    }
}

<?php

namespace App\Modules\Status\Actions;

use App\Modules\Status\Enums\Impact;
use App\Modules\Status\Enums\IncidentState;
use App\Modules\Status\Models\Check;
use App\Modules\Status\Models\Component;
use App\Modules\Status\Models\Incident;
use App\Modules\Status\Probes\Probe;
use App\Modules\Status\Support\StatusComponents;
use App\Modules\Status\Support\StatusSummary;
use Illuminate\Support\Facades\DB;

/**
 * Roda as sondas, grava as medições e abre/resolve incidentes automáticos: `fail_threshold` falhas seguidas abrem
 * ("Investigando"), `recover_threshold` sucessos seguidos resolvem. Em manutenção em curso nada é aberto.
 */
class RunProbes
{
    /**
     * @param  list<Probe>|null  $probes
     * @return array<string, bool> chave do componente => ok (só os que foram sondados)
     */
    public function handle(?array $probes = null): array
    {
        $components = StatusComponents::ensure()->keyBy('key');
        $results = [];

        foreach ($probes ?? StatusComponents::probes() as $probe) {
            $component = $components->get($probe->key());
            $result = $probe->run();

            if ($component === null || $result->skipped) {
                continue;
            }

            Check::query()->create([
                'component_id' => $component->getKey(),
                'ok' => $result->ok,
                'latency_ms' => $result->latencyMs,
                'detail' => $result->detail,
                'checked_at' => now(),
            ]);

            $results[$probe->key()] = $result->ok;
            $this->track($component, $result->ok);
        }

        StatusSummary::forget();

        return $results;
    }

    private function track(Component $component, bool $ok): void
    {
        $open = Incident::query()->openIncidents()->where('auto', true)->whereHas('components', fn ($query) => $query->whereKey($component->getKey()))->first();

        if ($ok) {
            if ($open !== null && $this->lastAre($component, true, (int) config('platform.status.recover_threshold', 2))) {
                $this->resolve($open);
            }

            return;
        }

        if ($open === null && $this->lastAre($component, false, (int) config('platform.status.fail_threshold', 3)) && ! $this->underMaintenance($component)) {
            $this->open($component);
        }
    }

    /** As últimas `$count` medições do componente têm todas o resultado `$ok` (e existem `$count` delas). */
    private function lastAre(Component $component, bool $ok, int $count): bool
    {
        $last = Check::query()->where('component_id', $component->getKey())->orderByDesc('checked_at')->orderByDesc('id')->limit($count)->pluck('ok');

        return $last->count() === $count && $last->every(fn ($value) => (bool) $value === $ok);
    }

    private function underMaintenance(Component $component): bool
    {
        return Incident::query()->pendingMaintenance()->whereHas('components', fn ($query) => $query->whereKey($component->getKey()))->get()
            ->contains(fn (Incident $incident) => $incident->maintenanceActive());
    }

    private function open(Component $component): void
    {
        DB::transaction(function () use ($component) {
            $incident = Incident::query()->create([
                'title' => "Instabilidade: {$component->name}",
                'impact' => Impact::Major,
                'state' => IncidentState::Investigating,
                'auto' => true,
                'starts_at' => now(),
            ]);
            $incident->components()->attach($component->getKey());
            $incident->updates()->create(['state' => IncidentState::Investigating, 'body' => 'Detectamos falhas neste serviço e estamos investigando.', 'created_at' => now()]);
        });
    }

    private function resolve(Incident $incident): void
    {
        DB::transaction(function () use ($incident) {
            $incident->update(['state' => IncidentState::Resolved, 'resolved_at' => now()]);
            $incident->updates()->create(['state' => IncidentState::Resolved, 'body' => 'O serviço voltou ao normal.', 'created_at' => now()]);
        });
    }
}

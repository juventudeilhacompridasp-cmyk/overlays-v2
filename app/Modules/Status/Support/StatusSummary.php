<?php

namespace App\Modules\Status\Support;

use App\Modules\Status\Enums\Impact;
use App\Modules\Status\Models\Component;
use App\Modules\Status\Models\Incident;
use App\Modules\Status\Models\IncidentUpdate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Retrato público da situação: geral, componentes (situação atual, últimos 90 dias e latência), incidentes recentes e
 * manutenções. SÓ dados técnicos da plataforma; nada de organização, atleta ou token. Cache curto (30 s).
 */
class StatusSummary
{
    public const DAYS = 90;

    private const CACHE_KEY = 'status:payload';

    private const STATUS_LABELS = ['operational' => 'Operacional', 'degraded' => 'Instabilidade', 'outage' => 'Fora do ar', 'maintenance' => 'Em manutenção'];

    private const OVERALL_LABELS = [
        'operational' => 'Todos os sistemas operacionais',
        'degraded' => 'Instabilidade em alguns serviços',
        'outage' => 'Serviços fora do ar',
        'maintenance' => 'Manutenção em andamento',
    ];

    /** @var array<string, int> */
    private const SEVERITY = ['operational' => 0, 'maintenance' => 1, 'degraded' => 2, 'outage' => 3];

    /** @return array<string, mixed> */
    public static function get(): array
    {
        return Cache::remember(self::CACHE_KEY, 30, fn () => self::build());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public static function build(?Carbon $now = null): array
    {
        $now ??= now();
        $components = StatusComponents::ensure()->where('visible', true)->values();
        $open = Incident::query()->openIncidents()->with('components')->get();
        $maintenance = Incident::query()->pendingMaintenance()->with('components')->get();
        $days = self::days($now);
        $latest = self::latest();

        $rows = [];

        foreach ($components as $component) {
            $status = self::statusOf($component, $open, $maintenance, $latest[$component->getKey()] ?? null, $now);
            $history = $days[$component->getKey()] ?? [];
            $up = array_sum(array_column($history, 'up'));
            $total = array_sum(array_column($history, 'total'));

            $rows[] = [
                'key' => $component->key,
                'name' => $component->name,
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status],
                'uptime' => $total > 0 ? round($up / $total * 100, 2) : null,
                'latency_ms' => isset($latest[$component->getKey()]) ? $latest[$component->getKey()]['latency_ms'] : null,
                'days' => self::series($history, $now),
            ];
        }

        $overall = 'operational';

        foreach ($rows as $row) {
            if (self::SEVERITY[$row['status']] > self::SEVERITY[$overall]) {
                $overall = $row['status'];
            }
        }

        return [
            'overall' => $overall,
            'overall_label' => self::OVERALL_LABELS[$overall],
            'updated_at' => $now->copy()->utc()->toIso8601String(),
            'components' => $rows,
            'incidents' => Incident::query()->where('impact', '!=', Impact::Maintenance->value)
                ->where(fn ($query) => $query->whereNull('resolved_at')->orWhere('resolved_at', '>=', $now->copy()->subDays(14)))
                ->with(['components', 'updates'])->orderByDesc('starts_at')->limit(20)->get()
                ->map(fn (Incident $incident) => self::incident($incident))->all(),
            'maintenance' => $maintenance->load('updates')->sortBy('starts_at')->values()->map(fn (Incident $incident) => self::incident($incident))->all(),
        ];
    }

    /**
     * Situação do componente: incidente aberto manda (pelo impacto), depois manutenção em curso, depois a última medição.
     *
     * @param  \Illuminate\Support\Collection<int, Incident>  $open
     * @param  \Illuminate\Support\Collection<int, Incident>  $maintenance
     * @param  array{ok: bool, latency_ms: int|null}|null  $latest
     */
    private static function statusOf(Component $component, $open, $maintenance, ?array $latest, Carbon $now): string
    {
        $status = 'operational';

        foreach ($open as $incident) {
            if ($incident->components->contains('id', $component->getKey())) {
                $candidate = $incident->impact->componentStatus();
                $status = self::SEVERITY[$candidate] > self::SEVERITY[$status] ? $candidate : $status;
            }
        }

        if ($status === 'operational' && $maintenance->contains(fn (Incident $incident) => $incident->maintenanceActive($now) && $incident->components->contains('id', $component->getKey()))) {
            return 'maintenance';
        }

        // Falhou agora mas ainda sem incidente (abaixo do limite de falhas seguidas): já aparece como instabilidade.
        if ($status === 'operational' && $latest !== null && ! $latest['ok']) {
            return 'degraded';
        }

        return $status;
    }

    /** @return array<string, array{ok: bool, latency_ms: int|null}> última medição por componente */
    private static function latest(): array
    {
        $ids = DB::table('status_checks')->selectRaw('max(id) as id')->groupBy('component_id')->pluck('id');
        $out = [];

        foreach (DB::table('status_checks')->whereIn('id', $ids)->get(['component_id', 'ok', 'latency_ms']) as $row) {
            $out[(string) $row->component_id] = ['ok' => (bool) $row->ok, 'latency_ms' => $row->latency_ms === null ? null : (int) $row->latency_ms];
        }

        return $out;
    }

    /** @return array<string, array<string, array{up: int, total: int}>> componente => dia => contagens */
    private static function days(Carbon $now): array
    {
        $rows = DB::table('status_checks')
            ->selectRaw('component_id, date(checked_at) as day, count(*) as total, sum(case when ok then 1 else 0 end) as up')
            ->where('checked_at', '>=', $now->copy()->subDays(self::DAYS - 1)->startOfDay())
            ->groupBy('component_id', DB::raw('date(checked_at)'))
            ->get();
        $out = [];

        foreach ($rows as $row) {
            $out[(string) $row->component_id][(string) $row->day] = ['up' => (int) $row->up, 'total' => (int) $row->total];
        }

        return $out;
    }

    /**
     * Série dos últimos 90 dias (do mais antigo ao mais novo); `uptime` nulo = sem medições naquele dia.
     *
     * @param  array<string, array{up: int, total: int}>  $history
     * @return list<array{date: string, uptime: float|null}>
     */
    private static function series(array $history, Carbon $now): array
    {
        $series = [];

        for ($offset = self::DAYS - 1; $offset >= 0; $offset--) {
            $date = $now->copy()->subDays($offset)->toDateString();
            $day = $history[$date] ?? null;
            $series[] = ['date' => $date, 'uptime' => $day !== null && $day['total'] > 0 ? round($day['up'] / $day['total'] * 100, 2) : null];
        }

        return $series;
    }

    /** @return array<string, mixed> */
    private static function incident(Incident $incident): array
    {
        return [
            'id' => $incident->getKey(),
            'title' => $incident->title,
            'impact' => $incident->impact->value,
            'impact_label' => $incident->impact->label(),
            'state' => $incident->state->value,
            'state_label' => $incident->state->label(),
            'auto' => $incident->auto,
            'starts_at' => $incident->starts_at->copy()->utc()->toIso8601String(),
            'ends_at' => $incident->ends_at?->copy()->utc()->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->copy()->utc()->toIso8601String(),
            'components' => $incident->components->pluck('name')->values()->all(),
            'updates' => $incident->updates->sortByDesc('created_at')->values()->map(fn (IncidentUpdate $update) => [
                'state' => $update->state->value,
                'state_label' => $update->state->label(),
                'body' => $update->body,
                'at' => $update->created_at->copy()->utc()->toIso8601String(),
            ])->all(),
        ];
    }
}

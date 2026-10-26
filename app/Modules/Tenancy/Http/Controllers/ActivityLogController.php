<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Support\SeasonReports;
use App\Modules\Tenancy\Models\ActivityLog;
use App\Modules\Tenancy\Support\ActivitySettings;
use App\Support\Tables\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Painel > Registro de atividades (só quem gerencia usuários): auditoria de tudo o que os usuários fizeram — ações de escrita
 * no painel, no gestor, no atleta e na plataforma, e as mudanças de jogo e lances com antes/depois. Lista paginada no banco
 * (filtros por pessoa, área, resultado, tipo, jogo e período) e exportação CSV com os mesmos filtros.
 */
class ActivityLogController extends Controller
{
    public const AREAS = ['panel' => 'Painel', 'manager' => 'Gestor de equipe', 'athlete' => 'Atleta', 'platform' => 'Plataforma', 'account' => 'Conta'];

    public const OUTCOMES = ['ok' => 'Feito', 'refused' => 'Recusado', 'failed' => 'Erro'];

    public const KINDS = ['request' => 'Ação', 'change' => 'Mudança'];

    public function index(Request $request): Response
    {
        $table = TableQuery::fromRequest($request, ['when', 'actor', 'summary', 'outcome'], 'when', ['actor', 'area', 'outcome', 'kind'], 'desc', 25);
        $query = $this->filtered($request, $table, ActivityLog::query());
        $table->sort($query, [
            'when' => 'created_at',
            'actor' => ['column' => 'actor_name', 'text' => true],
            'summary' => ['column' => 'summary', 'text' => true],
            'outcome' => 'outcome',
        ]);

        $entries = $query->paginate($table->perPage)->withQueryString()->through(fn (ActivityLog $entry) => [
            'id' => $entry->getKey(),
            'at' => $entry->created_at->toIso8601String(),
            'actor' => $entry->actor_name,
            'role' => $entry->actor_role,
            'area' => self::AREAS[$entry->area] ?? $entry->area,
            'kind' => self::KINDS[$entry->kind] ?? $entry->kind,
            'summary' => $entry->summary,
            'subject' => $entry->subject_label,
            'game_id' => $entry->game_id,
            'outcome' => $entry->outcome,
            'outcome_label' => self::OUTCOMES[$entry->outcome] ?? $entry->outcome,
            'fields' => $entry->fields ?? [],
            'changes' => $entry->changes ?? [],
        ]);

        return Inertia::render('Panel/ActivityLog/Index', [
            'entries' => $entries,
            'filters' => $table->props(),
            'actors' => ActivityLog::query()->whereNotNull('user_id')->select('user_id', 'actor_name')->distinct()->orderBy('actor_name')->limit(200)->get()
                ->map(fn ($row) => ['value' => (string) $row->user_id, 'label' => $row->actor_name])->values(),
            'areas' => collect(self::AREAS)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'outcomes' => collect(self::OUTCOMES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'kinds' => collect(self::KINDS)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'game' => $this->gameFilter($request),
            'period' => ['from' => $this->date($request->query('from')), 'to' => $this->date($request->query('to'))],
            'retention_days' => ActivitySettings::retentionDays(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $table = TableQuery::fromRequest($request, ['when'], 'when', ['actor', 'area', 'outcome', 'kind'], 'desc');
        $query = $this->filtered($request, $table, ActivityLog::query())->orderByDesc('created_at')->orderByDesc('id')->limit(5000);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos corretamente
            fputcsv($out, ['Data', 'Quem', 'Papel', 'Área', 'Tipo', 'O que foi feito', 'Em que', 'Resultado', 'Campos enviados', 'Mudanças'], ';');

            foreach ($query->get() as $entry) {
                $changes = collect($entry->changes ?? [])->map(fn ($pair, $field) => "{$field}: ".json_encode($pair[0] ?? null).' → '.json_encode($pair[1] ?? null))->implode(' | ');
                $row = [
                    $entry->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
                    $entry->actor_name,
                    (string) $entry->actor_role,
                    self::AREAS[$entry->area] ?? $entry->area,
                    self::KINDS[$entry->kind] ?? $entry->kind,
                    $entry->summary,
                    (string) $entry->subject_label,
                    self::OUTCOMES[$entry->outcome] ?? $entry->outcome,
                    implode(', ', $entry->fields ?? []),
                    $changes,
                ];
                fputcsv($out, array_map(fn (string $value) => SeasonReports::safeCell($value), $row), ';');
            }

            fclose($out);
        }, 'registro-de-atividades-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    private function filtered(Request $request, TableQuery $table, Builder $query): Builder
    {
        $table->search($query, ['actor_name', 'summary', 'subject_label', 'action']);
        $table->filter($query, [
            'actor' => fn ($builder, string $value) => $builder->where('user_id', (int) $value),
            'area' => fn ($builder, string $value) => $builder->where('area', $value),
            'outcome' => fn ($builder, string $value) => $builder->where('outcome', $value),
            'kind' => fn ($builder, string $value) => $builder->where('kind', $value),
        ]);

        $game = $request->query('game');

        if (is_string($game) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $game) === 1) {
            $query->where('game_id', strtolower($game));
        }

        if (($from = $this->date($request->query('from'))) !== null) {
            $query->where('created_at', '>=', Carbon::parse($from, config('app.timezone'))->startOfDay());
        }

        if (($to = $this->date($request->query('to'))) !== null) {
            $query->where('created_at', '<=', Carbon::parse($to, config('app.timezone'))->endOfDay());
        }

        return $query;
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }

    /** @return array{id: string, label: string|null}|null */
    private function gameFilter(Request $request): ?array
    {
        $game = $request->query('game');

        if (! is_string($game) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $game) !== 1) {
            return null;
        }

        $label = ActivityLog::query()->where('game_id', strtolower($game))->whereNotNull('subject_label')->latest('created_at')->value('subject_label');

        return ['id' => strtolower($game), 'label' => $label];
    }
}

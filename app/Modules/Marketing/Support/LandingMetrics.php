<?php

namespace App\Modules\Marketing\Support;

use App\Modules\Marketing\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Medição anônima e agregada da landing page. O navegador só envia eventos DEPOIS do consentimento (e nunca com "Do Not
 * Track"/GPC); o servidor guarda apenas contadores por dia, de uma lista FECHADA de métricas e dimensões (qualquer outra
 * coisa é descartada). Sem IP, user agent, cookie de rastreio, identificador ou URL.
 */
class LandingMetrics
{
    public const MAX_EVENTS = 10;

    /** Fuso dos "dias" (o mesmo da operação). */
    public const TIMEZONE = 'America/Sao_Paulo';

    /** Métricas e as dimensões aceitas (`visit` não tem dimensão). Rótulos em português para o painel. */
    public const METRICS = [
        'visit' => ['label' => 'Visitas', 'dimensions' => ['' => 'Visitas']],
        'source' => ['label' => 'De onde vieram', 'dimensions' => ['direct' => 'Direto', 'search' => 'Buscadores', 'social' => 'Redes sociais', 'other' => 'Outros sites']],
        'device' => ['label' => 'Aparelho', 'dimensions' => ['mobile' => 'Celular', 'desktop' => 'Computador']],
        'section' => ['label' => 'Seções vistas', 'dimensions' => ['galeria' => 'O produto em ação', 'pacotes' => 'Pacotes', 'faq' => 'Dúvidas frequentes', 'contato' => 'Formulário de contato']],
        'cta' => ['label' => 'Cliques nos botões', 'dimensions' => ['menu' => 'Falar com a equipe (menu)', 'hero' => 'Quero conhecer (topo)', 'contato' => 'Enviar mensagem (formulário)']],
    ];

    public static function enabled(): bool
    {
        return config('platform.landing.analytics') === true;
    }

    public static function valid(string $metric, string $dimension): bool
    {
        return isset(self::METRICS[$metric]['dimensions']) && array_key_exists($dimension, self::METRICS[$metric]['dimensions']);
    }

    public static function today(?Carbon $now = null): string
    {
        return ($now ?? now())->copy()->timezone(self::TIMEZONE)->toDateString();
    }

    /**
     * Soma 1 a cada evento válido (pares repetidos no mesmo envio contam uma vez). Devolve quantos foram aceitos.
     *
     * @param  list<array{metric?: mixed, dimension?: mixed}>  $events
     */
    public static function record(array $events): int
    {
        $accepted = [];

        foreach (array_slice($events, 0, self::MAX_EVENTS) as $event) {
            $metric = is_string($event['metric'] ?? null) ? $event['metric'] : '';
            $dimension = is_string($event['dimension'] ?? null) ? $event['dimension'] : '';

            if (self::valid($metric, $dimension)) {
                $accepted["{$metric}|{$dimension}"] = [$metric, $dimension];
            }
        }

        $day = self::today();

        foreach ($accepted as [$metric, $dimension]) {
            DB::statement(
                'insert into marketing_metrics (day, metric, dimension, total) values (?, ?, ?, 1) on conflict (day, metric, dimension) do update set total = marketing_metrics.total + 1',
                [$day, $metric, $dimension],
            );
        }

        return count($accepted);
    }

    /**
     * Resumo dos últimos `$days` dias (incluindo hoje): visitas e contatos por dia, totais, conversão aproximada e
     * as quebras por origem, aparelho, seção e botão (cada uma como parcela das visitas medidas).
     *
     * @return array<string, mixed>
     */
    public static function summary(int $days, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone(self::TIMEZONE);
        $from = $now->copy()->subDays($days - 1)->startOfDay();
        $rows = DB::table('marketing_metrics')->where('day', '>=', $from->toDateString())->get(['day', 'metric', 'dimension', 'total']);

        $visitsByDay = [];
        $totals = [];

        foreach ($rows as $row) {
            if ($row->metric === 'visit') {
                $visitsByDay[(string) $row->day] = ($visitsByDay[(string) $row->day] ?? 0) + (int) $row->total;
            }

            $key = $row->metric.'|'.$row->dimension;
            $totals[$key] = ($totals[$key] ?? 0) + (int) $row->total;
        }

        $leadsByDay = [];

        foreach (Lead::query()->where('created_at', '>=', $from->copy()->utc())->pluck('created_at') as $createdAt) {
            $day = Carbon::parse($createdAt)->timezone(self::TIMEZONE)->toDateString();
            $leadsByDay[$day] = ($leadsByDay[$day] ?? 0) + 1;
        }

        $daily = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->copy()->addDays($offset)->toDateString();
            $daily[] = ['date' => $date, 'visits' => $visitsByDay[$date] ?? 0, 'leads' => $leadsByDay[$date] ?? 0];
        }

        $visits = array_sum(array_column($daily, 'visits'));
        $leads = array_sum(array_column($daily, 'leads'));
        $breakdown = [];

        foreach (['source', 'device', 'section', 'cta'] as $metric) {
            $items = [];

            foreach (self::METRICS[$metric]['dimensions'] as $dimension => $label) {
                $count = $totals["{$metric}|{$dimension}"] ?? 0;
                $items[] = ['key' => (string) $dimension, 'label' => $label, 'count' => $count, 'percent' => $visits > 0 ? round($count / $visits * 100, 1) : null];
            }

            usort($items, fn (array $a, array $b) => $b['count'] <=> $a['count']);
            $breakdown[$metric] = ['label' => self::METRICS[$metric]['label'], 'items' => $items];
        }

        return [
            'days' => $days,
            'from' => $from->toDateString(),
            'to' => $now->toDateString(),
            'daily' => $daily,
            'totals' => ['visits' => $visits, 'leads' => $leads, 'conversion' => $visits > 0 ? round($leads / $visits * 100, 1) : null],
            'breakdown' => $breakdown,
        ];
    }

    /** Apaga contadores mais antigos que `$days` dias. */
    public static function prune(int $days = 400): int
    {
        return DB::table('marketing_metrics')->where('day', '<', now()->timezone(self::TIMEZONE)->subDays($days)->toDateString())->delete();
    }
}

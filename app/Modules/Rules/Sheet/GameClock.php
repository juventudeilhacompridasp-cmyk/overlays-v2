<?php

namespace App\Modules\Rules\Sheet;

/**
 * Cronômetro do jogo derivado dos marcos de período (início/fim). Código puro: o cliente faz a contagem
 * a partir de `base_seconds` (+ o tempo desde `running_since_ms`, quando em andamento); o servidor só informa os marcos.
 *
 * O relógio de cada período começa no tempo nominal acumulado dos anteriores (2º tempo começa em 45:00).
 */
final class GameClock
{
    public const NOT_STARTED = 'not_started';

    public const RUNNING = 'running';

    public const STOPPED = 'stopped';

    /** @param  list<int>  $periodMinutes duração nominal de cada período, em minutos */
    public function __construct(private readonly array $periodMinutes = [45, 45, 15, 15]) {}

    public function maxPeriod(): int
    {
        return count($this->periodMinutes);
    }

    /** Segundo do relógio em que o período começa. */
    public function offsetSeconds(int $period): int
    {
        return array_sum(array_slice($this->periodMinutes, 0, max(0, $period - 1))) * 60;
    }

    /**
     * Mensagem de erro se o marco não for permitido, ou null. `$marks`: marcos já registrados.
     *
     * @param  list<ClockMark>  $marks
     */
    public function check(array $marks, ClockMark $next): ?string
    {
        if ($next->period < 1 || $next->period > $this->maxPeriod()) {
            return 'Período inválido.';
        }

        $started = [];
        $ended = [];

        foreach ($marks as $mark) {
            if ($mark->start) {
                $started[$mark->period] = true;
            } else {
                $ended[$mark->period] = true;
            }
        }

        if ($next->start) {
            if (isset($started[$next->period])) {
                return 'Este período já foi iniciado.';
            }

            if ($next->period > 1 && ! isset($ended[$next->period - 1])) {
                return 'Encerre o período anterior antes de iniciar o próximo.';
            }

            return null;
        }

        if (! isset($started[$next->period])) {
            return 'Este período ainda não foi iniciado.';
        }

        return isset($ended[$next->period]) ? 'Este período já foi encerrado.' : null;
    }

    /**
     * @param  list<ClockMark>  $marks  em ordem cronológica
     * @return array{state: string, period: int|null, base_seconds: int, running_since_ms: int|null}
     */
    public function snapshot(array $marks): array
    {
        $current = null;
        $startMs = null;
        $endMs = null;

        foreach ($marks as $mark) {
            if ($mark->start) {
                $current = $mark->period;
                $startMs = $mark->atMs;
                $endMs = null;
            } elseif ($mark->period === $current) {
                $endMs = $mark->atMs;
            }
        }

        if ($current === null || $startMs === null) {
            return ['state' => self::NOT_STARTED, 'period' => null, 'base_seconds' => 0, 'running_since_ms' => null];
        }

        $offset = $this->offsetSeconds($current);

        if ($endMs === null) {
            return ['state' => self::RUNNING, 'period' => $current, 'base_seconds' => $offset, 'running_since_ms' => $startMs];
        }

        return [
            'state' => self::STOPPED,
            'period' => $current,
            'base_seconds' => $offset + max(0, intdiv($endMs - $startMs, 1000)),
            'running_since_ms' => null,
        ];
    }
}

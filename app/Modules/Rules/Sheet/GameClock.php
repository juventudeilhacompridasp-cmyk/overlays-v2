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

    /** Relógio parado no meio do período (basquete). */
    public const PAUSED = 'paused';

    /** @param  list<int>  $periodMinutes duração nominal de cada período, em minutos */
    public function __construct(private readonly array $periodMinutes = [45, 45, 15, 15]) {}

    /** Duração nominal do período, em minutos (0 se o período não existe). */
    public function periodMinutes(int $period): int
    {
        return $this->periodMinutes[$period - 1] ?? 0;
    }

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
        $paused = false;

        foreach ($marks as $mark) {
            if ($mark->isPause()) {
                $paused = ! $mark->start;

                continue;
            }

            $paused = false;

            if ($mark->start) {
                $started[$mark->period] = true;
            } else {
                $ended[$mark->period] = true;
            }
        }

        if ($next->isPause()) {
            $running = isset($started[$next->period]) && ! isset($ended[$next->period]);

            if (! $running) {
                return 'O período não está em andamento.';
            }

            if ($next->start) {
                return $paused ? null : 'O relógio não está parado.';
            }

            return $paused ? 'O relógio já está parado.' : null;
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
        $runningSince = null;
        $accumulatedMs = 0;
        $ended = false;

        foreach ($marks as $mark) {
            if ($mark->isPause()) {
                if ($mark->period !== $current || $ended) {
                    continue;
                }

                if ($mark->start) {
                    $runningSince ??= $mark->atMs; // retomada
                } elseif ($runningSince !== null) {
                    $accumulatedMs += max(0, $mark->atMs - $runningSince); // pausa
                    $runningSince = null;
                }

                continue;
            }

            if ($mark->start) {
                $current = $mark->period;
                $runningSince = $mark->atMs;
                $accumulatedMs = 0;
                $ended = false;
            } elseif ($mark->period === $current) {
                if ($runningSince !== null) {
                    $accumulatedMs += max(0, $mark->atMs - $runningSince);
                    $runningSince = null;
                }

                $ended = true;
            }
        }

        if ($current === null) {
            return ['state' => self::NOT_STARTED, 'period' => null, 'base_seconds' => 0, 'running_since_ms' => null];
        }

        $base = $this->offsetSeconds($current) + intdiv($accumulatedMs, 1000);

        if ($ended) {
            return ['state' => self::STOPPED, 'period' => $current, 'base_seconds' => $base, 'running_since_ms' => null];
        }

        if ($runningSince === null) {
            return ['state' => self::PAUSED, 'period' => $current, 'base_seconds' => $base, 'running_since_ms' => null];
        }

        return ['state' => self::RUNNING, 'period' => $current, 'base_seconds' => $base, 'running_since_ms' => $runningSince];
    }
}

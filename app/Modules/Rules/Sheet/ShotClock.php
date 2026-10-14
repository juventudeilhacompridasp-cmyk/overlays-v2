<?php

namespace App\Modules\Rules\Sheet;

/** Relógio de posse derivado dos marcos do relógio principal e dos reinícios em 14/24 segundos. */
final class ShotClock
{
    /**
     * @param  list<array{type: string, period: int, at_ms: int, seconds?: int}>  $events
     * @return array{state: string, period: int|null, base_seconds: int, running_since_ms: int|null}
     */
    public function snapshot(array $events): array
    {
        $period = null;
        $remainingMs = 0;
        /** @var int|null $runningSince */
        $runningSince = null;
        $state = GameClock::NOT_STARTED;

        $consume = static function (int $atMs) use (&$remainingMs, &$runningSince): void {
            if ($runningSince !== null) {
                $remainingMs = max(0, $remainingMs - max(0, $atMs - $runningSince));
                $runningSince = null;
            }
        };

        foreach ($events as $event) {
            $type = $event['type'];
            $atMs = $event['at_ms'];

            if ($type === 'period_start') {
                $period = $event['period'];
                $remainingMs = 24_000;
                $runningSince = $atMs;
                $state = GameClock::RUNNING;
            } elseif ($type === 'clock_pause' && $event['period'] === $period) {
                $consume($atMs);
                $state = GameClock::PAUSED;
            } elseif ($type === 'clock_resume' && $event['period'] === $period) {
                if ($remainingMs > 0) {
                    $runningSince = $atMs;
                }
                $state = GameClock::RUNNING;
            } elseif ($type === 'period_end' && $event['period'] === $period) {
                $consume($atMs);
                $state = GameClock::STOPPED;
            } elseif ($type === 'shot_clock_reset' && $event['period'] === $period && $state !== GameClock::STOPPED) {
                $remainingMs = ($event['seconds'] ?? 24) * 1000;
                $runningSince = $state === GameClock::RUNNING ? $atMs : null;
            }
        }

        return [
            'state' => $state,
            'period' => $period,
            'base_seconds' => (int) ceil($remainingMs / 1000),
            'running_since_ms' => $remainingMs > 0 ? $runningSince : null,
        ];
    }
}

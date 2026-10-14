<?php

namespace App\Modules\Broadcast\Support;

/**
 * Etapas da apresentação da escalação por equipe (cards/individual/show). Em `show`, a etapa 0 é a grade e as etapas 1..n
 * são os jogadores; em `individual`, as etapas 0..n-1 são os jogadores. No modo automático cada etapa dura um tempo e a
 * etapa atual sai do relógio do servidor (`anchor_ms`), então todas as telas abertas mostram a mesma coisa; no manual vale
 * `step`. A mesma regra existe no front (`resources/js/lib/overlayLineup.ts`); mude as duas juntas.
 */
final class OverlayLineupTimeline
{
    public static function steps(string $presentation, int $individuals): int
    {
        return match ($presentation) {
            'show' => $individuals + 1,
            'individual' => max(1, $individuals),
            default => 1,
        };
    }

    /** @return list<int> início (ms depois da âncora) de cada etapa do modo automático */
    public static function starts(string $presentation, int $listSeconds, int $individualSeconds, int $individuals): array
    {
        $starts = [];
        $at = 0;

        foreach (range(0, self::steps($presentation, $individuals) - 1) as $step) {
            $starts[] = $at;
            $at += ($presentation === 'show' && $step === 0 ? $listSeconds : $individualSeconds) * 1000;
        }

        return $starts;
    }

    /**
     * Comando do operador sobre as configurações já normalizadas. Devolve `step` e `anchor_ms` novos.
     *
     * @param  array<string, mixed>  $settings
     * @return array{step: int, anchor_ms: int}
     */
    public static function apply(string $command, array $settings, int $individuals, int $nowMs): array
    {
        $presentation = (string) $settings['presentation'];
        $total = self::steps($presentation, $individuals);

        if ($command === 'reset') {
            return ['step' => 0, 'anchor_ms' => $nowMs];
        }

        if ($settings['individual_mode'] === 'manual') {
            $step = (int) $settings['step'] + ($command === 'next' ? 1 : -1);

            if ($step > $total - 1) {
                $step = $presentation === 'show' ? 0 : $total - 1;
            }

            return ['step' => max(0, $step), 'anchor_ms' => (int) $settings['anchor_ms']];
        }

        // Automático: salta o relógio para o início da etapa seguinte/anterior.
        $starts = self::starts($presentation, (int) $settings['list_seconds'], (int) $settings['individual_seconds'], $individuals);
        $elapsed = max(0, $nowMs - (int) $settings['anchor_ms']);
        $current = 0;

        foreach ($starts as $index => $start) {
            if ($elapsed >= $start) {
                $current = $index;
            }
        }

        $target = $command === 'next' ? ($current + 1 > $total - 1 ? ($presentation === 'show' ? 0 : $total - 1) : $current + 1) : max(0, $current - 1);

        return ['step' => (int) $settings['step'], 'anchor_ms' => $nowMs - $starts[$target]];
    }
}

<?php

namespace App\Modules\Broadcast\Support;

/**
 * Etapas da apresentação da escalação por equipe (cards/individual/show/full). Cada etapa tem um tipo (`grid` = grade de
 * cartões, `formation` = esquema tático da equipe, `individual` = um jogador) e uma duração:
 * - `show`: grade e depois cada jogador;
 * - `individual`: só os jogadores;
 * - `full`: grade, esquema tático (só se a equipe tem formação) e (opcional) cada jogador.
 * No modo automático a etapa atual sai do relógio do servidor (`anchor_ms`), então todas as telas abertas mostram a mesma
 * coisa; no manual vale `step`. A mesma regra existe no front (`resources/js/lib/overlayLineup.ts`); mude as duas juntas.
 */
final class OverlayLineupTimeline
{
    /**
     * @param  array<string, mixed>  $settings  configurações normalizadas
     * @return list<array{kind: string, ms: int}>
     */
    public static function plan(array $settings, int $individuals, bool $formation = false): array
    {
        $list = (int) $settings['list_seconds'] * 1000;
        $each = (int) $settings['individual_seconds'] * 1000;
        $people = fn (): array => array_fill(0, $individuals, ['kind' => 'individual', 'ms' => $each]);

        return match ((string) $settings['presentation']) {
            'show' => [['kind' => 'grid', 'ms' => $list], ...$people()],
            'individual' => $individuals > 0 ? $people() : [['kind' => 'individual', 'ms' => $each]],
            'full' => [
                ['kind' => 'grid', 'ms' => $list],
                ...($formation ? [['kind' => 'formation', 'ms' => (int) $settings['formation_seconds'] * 1000]] : []),
                ...($settings['full_individuals'] ? $people() : []),
            ],
            default => [['kind' => 'grid', 'ms' => $list]],
        };
    }

    /** @param  array<string, mixed>  $settings */
    public static function steps(array|string $settings, int $individuals, bool $formation = false): int
    {
        if (is_string($settings)) {
            // Chamada antiga (só a apresentação): usa os padrões das demais configurações.
            $settings = [...OverlayLineupSettings::defaults(), 'presentation' => $settings];
        }

        return count(self::plan($settings, $individuals, $formation));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<int> início (ms depois da âncora) de cada etapa do modo automático
     */
    public static function starts(array $settings, int $individuals, bool $formation = false): array
    {
        $starts = [];
        $at = 0;

        foreach (self::plan($settings, $individuals, $formation) as $step) {
            $starts[] = $at;
            $at += $step['ms'];
        }

        return $starts;
    }

    /** Depois da última etapa `show` e `full` voltam à grade; `individual` fica no último jogador. */
    private static function wraps(string $presentation): bool
    {
        return in_array($presentation, ['show', 'full'], true);
    }

    /**
     * Comando do operador sobre as configurações já normalizadas. Devolve `step` e `anchor_ms` novos.
     *
     * @param  array<string, mixed>  $settings
     * @return array{step: int, anchor_ms: int}
     */
    public static function apply(string $command, array $settings, int $individuals, int $nowMs, bool $formation = false): array
    {
        $presentation = (string) $settings['presentation'];
        $total = self::steps($settings, $individuals, $formation);

        if ($command === 'reset') {
            return ['step' => 0, 'anchor_ms' => $nowMs];
        }

        if ($settings['individual_mode'] === 'manual') {
            $step = (int) $settings['step'] + ($command === 'next' ? 1 : -1);

            if ($step > $total - 1) {
                $step = self::wraps($presentation) ? 0 : $total - 1;
            }

            return ['step' => max(0, $step), 'anchor_ms' => (int) $settings['anchor_ms']];
        }

        // Automático: salta o relógio para o início da etapa seguinte/anterior.
        $starts = self::starts($settings, $individuals, $formation);
        $elapsed = max(0, $nowMs - (int) $settings['anchor_ms']);
        $current = 0;

        foreach ($starts as $index => $start) {
            if ($elapsed >= $start) {
                $current = $index;
            }
        }

        $target = $command === 'next' ? ($current + 1 > $total - 1 ? (self::wraps($presentation) ? 0 : $total - 1) : $current + 1) : max(0, $current - 1);

        return ['step' => (int) $settings['step'], 'anchor_ms' => $nowMs - $starts[$target]];
    }
}

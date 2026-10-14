<?php

namespace App\Modules\Broadcast\Support;

/**
 * Cartela de transmissão ("Começamos em breve", "Intervalo", "Voltamos já"...): título, subtítulo e um fim de contagem
 * regressiva opcional (instante em milissegundos, calculado no servidor a partir de "minutos").
 */
final class OverlayScreenSettings
{
    /** @return array{title: string, subtitle: string, until_ms: int|null, stoppage: int} */
    public static function defaults(): array
    {
        return ['title' => 'Começamos em breve', 'subtitle' => '', 'until_ms' => null, 'stoppage' => 0];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{title: string, subtitle: string, until_ms: int|null, stoppage: int}
     */
    public static function normalize(?array $settings): array
    {
        $values = array_merge(self::defaults(), array_intersect_key($settings ?? [], self::defaults()));
        $until = is_numeric($values['until_ms']) && (int) $values['until_ms'] > 0 ? (int) $values['until_ms'] : null;

        return [
            'title' => mb_substr(trim((string) $values['title']), 0, 60),
            'subtitle' => mb_substr(trim((string) $values['subtitle']), 0, 80),
            'until_ms' => $until,
            'stoppage' => is_numeric($values['stoppage']) ? min(15, max(0, (int) $values['stoppage'])) : 0,
        ];
    }

    /**
     * Aplica o comando do operador (`title`, `subtitle`, `minutes`): os minutos viram o instante final da contagem.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $command
     * @return array{title: string, subtitle: string, until_ms: int|null, stoppage: int}
     */
    public static function apply(?array $current, array $command, int $nowMs): array
    {
        $next = array_merge(self::normalize($current), array_intersect_key($command, ['title' => 1, 'subtitle' => 1]));

        if (array_key_exists('minutes', $command)) {
            $minutes = is_numeric($command['minutes']) ? (int) $command['minutes'] : 0;
            $next['until_ms'] = $minutes > 0 ? $nowMs + min(180, $minutes) * 60_000 : null;
        }

        return self::normalize($next);
    }
}

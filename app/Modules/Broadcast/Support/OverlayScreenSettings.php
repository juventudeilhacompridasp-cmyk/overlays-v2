<?php

namespace App\Modules\Broadcast\Support;

/**
 * Cartela de transmissão ("Começamos em breve", "Intervalo", "Voltamos já"...): título, subtítulo e um fim de contagem
 * regressiva opcional (instante em milissegundos, calculado no servidor a partir de "minutos").
 */
final class OverlayScreenSettings
{
    /** @return array{title: string, subtitle: string, until_ms: int|null, stoppage: int, mvp: string|null, serve: string|null, social: array{items: list<array{label: string, value: string}>, url: string|null}} */
    public static function defaults(): array
    {
        return ['title' => 'Começamos em breve', 'subtitle' => '', 'until_ms' => null, 'stoppage' => 0, 'mvp' => null, 'serve' => null, 'social' => ['items' => [], 'url' => null]];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{title: string, subtitle: string, until_ms: int|null, stoppage: int, mvp: string|null, serve: string|null, social: array{items: list<array{label: string, value: string}>, url: string|null}}
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
            'serve' => in_array($values['serve'], ['home', 'away'], true) ? (string) $values['serve'] : null,
            'social' => self::social($values['social']),
            'mvp' => is_string($values['mvp']) && $values['mvp'] !== '' ? mb_substr($values['mvp'], 0, 40) : null,
        ];
    }

    /**
     * Aplica o comando do operador (`title`, `subtitle`, `minutes`): os minutos viram o instante final da contagem.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $command
     * @return array{title: string, subtitle: string, until_ms: int|null, stoppage: int, mvp: string|null, serve: string|null, social: array{items: list<array{label: string, value: string}>, url: string|null}}
     */
    public static function apply(?array $current, array $command, int $nowMs): array
    {
        $next = array_merge(self::normalize($current), array_intersect_key($command, ['title' => 1, 'subtitle' => 1]));

        if (array_key_exists('social', $command) && is_array($command['social'])) {
            $next['social'] = $command['social'];
        }

        if (array_key_exists('serve', $command)) {
            $next['serve'] = $command['serve'];
        }

        if (array_key_exists('mvp_person_id', $command)) {
            $next['mvp'] = is_string($command['mvp_person_id']) && $command['mvp_person_id'] !== '' ? $command['mvp_person_id'] : null;
        }

        if (array_key_exists('minutes', $command)) {
            $minutes = is_numeric($command['minutes']) ? (int) $command['minutes'] : 0;
            $next['until_ms'] = $minutes > 0 ? $nowMs + min(180, $minutes) * 60_000 : null;
        }

        return self::normalize($next);
    }

    /**
     * Chamada para redes sociais: até 4 linhas (rótulo + valor, ex.: "Instagram" / "@juventude") e um link https para o QR code.
     *
     * @return array{items: list<array{label: string, value: string}>, url: string|null}
     */
    private static function social(mixed $social): array
    {
        $social = is_array($social) ? $social : [];
        $items = [];

        foreach (is_array($social['items'] ?? null) ? $social['items'] : [] as $item) {
            $label = is_array($item) ? mb_substr(trim((string) ($item['label'] ?? '')), 0, 20) : '';
            $value = is_array($item) ? mb_substr(trim((string) ($item['value'] ?? '')), 0, 60) : '';

            if ($value !== '' && count($items) < 4) {
                $items[] = ['label' => $label, 'value' => $value];
            }
        }

        $url = is_string($social['url'] ?? null) ? trim($social['url']) : '';
        $valid = $url !== '' && mb_strlen($url) <= 200 && preg_match('#^https://[^\s/]+(/\S*)?$#i', $url) === 1;

        return ['items' => $items, 'url' => $valid ? $url : null];
    }
}

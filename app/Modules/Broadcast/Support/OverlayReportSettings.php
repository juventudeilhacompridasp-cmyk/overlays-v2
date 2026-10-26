<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Competitions\Models\Division;
use App\Modules\Reports\Enums\ReportKind;
use App\Modules\Reports\Support\ReportSections;

/**
 * Tela "Relatório" do overlay (módulo Reports, ADR 0015): qual relatório (partida no ar ou divisão), quais seções viram
 * slides, como passam (manual = o operador avança; automático = a cada `seconds`, contado de `anchor_ms`) e o visual.
 * A etapa é do SERVIDOR (como a da escalação) e mudar o que é mostrado recomeça do primeiro slide. Mesma conta do front
 * em `lib/report.ts` (`reportSlideAt`).
 */
final class OverlayReportSettings
{
    public const MODES = ['manual', 'auto'];

    public const STYLES = ['broadcast', 'glass', 'light'];

    public const ANIMATIONS = ['rise', 'slide', 'zoom', 'fade'];

    public const COMMANDS = ['next', 'previous', 'reset'];

    /** O que, se mudar, recomeça a apresentação do primeiro slide. */
    private const WATCHED = ['kind', 'division_id', 'sections', 'mode', 'seconds'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'kind' => ReportKind::Game->value, 'division_id' => null, 'sections' => ['overview', 'comparison', 'timeline'],
            'mode' => 'manual', 'seconds' => 12, 'step' => 0, 'anchor_ms' => 0,
            'style' => 'broadcast', 'animation' => 'rise', 'scale' => 100,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function normalize(?array $settings): array
    {
        $defaults = self::defaults();
        $values = array_merge($defaults, array_intersect_key($settings ?? [], $defaults));
        $kind = ReportKind::tryFrom((string) $values['kind']) ?? ReportKind::Game;
        $number = fn (mixed $value, int $min, int $max, int $fallback): int => is_numeric($value) ? min($max, max($min, (int) round((float) $value))) : $fallback;
        $choice = fn (mixed $value, array $allowed, string $fallback): string => is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
        $given = $settings['sections'] ?? null;
        $sections = is_array($given) && $given !== [] ? $given : ($kind === ReportKind::Game ? $defaults['sections'] : ['overview', 'standings', 'scorers']);

        return [
            'kind' => $kind->value,
            'division_id' => $kind === ReportKind::Championship && is_string($values['division_id']) && $values['division_id'] !== '' ? mb_substr($values['division_id'], 0, 40) : null,
            'sections' => ReportSections::pick($kind, $sections),
            'mode' => $choice($values['mode'], self::MODES, 'manual'),
            'seconds' => $number($values['seconds'], 5, 60, 12),
            'step' => $number($values['step'], 0, 999, 0),
            'anchor_ms' => is_numeric($values['anchor_ms']) ? max(0, (int) $values['anchor_ms']) : 0,
            'style' => $choice($values['style'], self::STYLES, 'broadcast'),
            'animation' => $choice($values['animation'], self::ANIMATIONS, 'rise'),
            'scale' => $number($values['scale'], 50, 150, 100),
        ];
    }

    /**
     * Aplica o que o operador mandou: só divisões da organização (as outras viram "a do jogo no ar"), e recomeça do primeiro
     * slide quando muda o que é mostrado ou o ritmo.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $command
     * @return array<string, mixed>
     */
    public static function apply(?array $current, array $command, int $nowMs): array
    {
        $before = self::normalize($current);

        if (array_key_exists('kind', $command) && $command['kind'] !== $before['kind'] && ! array_key_exists('sections', $command)) {
            $command['sections'] = []; // trocar de relatório volta às seções padrão daquele relatório
        }

        if (isset($command['division_id']) && is_string($command['division_id']) && ! Division::query()->whereKey($command['division_id'])->exists()) {
            $command['division_id'] = null;
        }

        $next = self::normalize(array_merge($before, array_diff_key($command, ['step' => 0, 'anchor_ms' => 0])));

        return self::changed($before, $next) ? self::restart($next, $nowMs) : $next;
    }

    /** @param  array<string, mixed>  $settings  @return array<string, mixed> */
    public static function restart(array $settings, int $nowMs): array
    {
        return [...self::normalize($settings), 'step' => 0, 'anchor_ms' => $nowMs];
    }

    /**
     * Próximo/anterior/reiniciar. No automático, o comando parte do slide que está na tela e reancora a contagem.
     *
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>
     */
    public static function command(?array $current, string $command, int $nowMs): array
    {
        $settings = self::normalize($current);
        $count = count($settings['sections']);
        $at = self::slideAt($settings, $count, $nowMs);

        return match ($command) {
            'next' => [...$settings, 'step' => ($at + 1) % max(1, $count), 'anchor_ms' => $nowMs],
            'previous' => [...$settings, 'step' => ($at - 1 + max(1, $count)) % max(1, $count), 'anchor_ms' => $nowMs],
            'reset' => self::restart($settings, $nowMs),
            default => $settings,
        };
    }

    /** Slide na tela agora (0 a count-1). Espelho de `reportSlideAt` em `lib/report.ts`. @param  array<string, mixed>  $settings */
    public static function slideAt(array $settings, int $count, int $nowMs): int
    {
        if ($count <= 0) {
            return 0;
        }

        $step = (int) $settings['step'];

        if ($settings['mode'] === 'auto') {
            $step += intdiv(max(0, $nowMs - (int) $settings['anchor_ms']), max(1, (int) $settings['seconds']) * 1000);
        }

        return $step % $count;
    }

    /** @param  array<string, mixed>  $a  @param  array<string, mixed>  $b */
    private static function changed(array $a, array $b): bool
    {
        return array_intersect_key($a, array_flip(self::WATCHED)) != array_intersect_key($b, array_flip(self::WATCHED));
    }
}

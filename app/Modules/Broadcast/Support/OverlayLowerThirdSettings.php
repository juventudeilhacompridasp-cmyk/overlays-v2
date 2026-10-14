<?php

namespace App\Modules\Broadcast\Support;

/**
 * Faixas de lance (lower thirds) por canal: estilo visual, animação, tempo no ar e quais lances aparecem. A aparência
 * geral (cores, fonte, escala) continua em `appearance`; aqui só o que é específico da faixa.
 */
final class OverlayLowerThirdSettings
{
    /** arena = cartão com ícone; broadcast = barra de TV; player = cartão do jogador com camisa; minimal = só texto; slash = diagonal esportiva; flash = faixa de ponta a ponta. */
    public const STYLES = ['arena', 'broadcast', 'player', 'minimal', 'slash', 'flash'];

    public const ANIMATIONS = ['slide', 'rise', 'zoom', 'wipe', 'flip', 'bounce', 'fade'];

    /** Lances que podem virar faixa (a substituição é a única que traz dois atletas). */
    public const KINDS = ['goal', 'own_goal', 'yellow_card', 'red_card', 'blue_card', 'substitution'];

    /** @return array{style: string, animation: string, seconds: int, kinds: list<string>, score: bool} */
    public static function defaults(): array
    {
        return ['style' => 'arena', 'animation' => 'slide', 'seconds' => 7, 'kinds' => self::KINDS, 'score' => true];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{style: string, animation: string, seconds: int, kinds: list<string>, score: bool}
     */
    public static function normalize(?array $settings): array
    {
        $values = array_merge(self::defaults(), array_intersect_key($settings ?? [], self::defaults()));
        $seconds = is_numeric($values['seconds']) ? (int) $values['seconds'] : self::defaults()['seconds'];
        $kinds = is_array($values['kinds']) ? array_values(array_intersect(self::KINDS, array_map('strval', $values['kinds']))) : self::KINDS;

        return [
            'style' => in_array($values['style'], self::STYLES, true) ? (string) $values['style'] : 'arena',
            'animation' => in_array($values['animation'], self::ANIMATIONS, true) ? (string) $values['animation'] : 'slide',
            'seconds' => min(15, max(3, $seconds)),
            'kinds' => $kinds,
            'score' => (bool) $values['score'],
        ];
    }
}

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

    /** Âncora horizontal da faixa (esquerda, centro, direita); a faixa de ponta a ponta ignora. */
    public const POSITIONS = ['left', 'center', 'right'];

    /** banner = faixa normal; fullscreen = cartão (amarelo, vermelho, azul) grande no centro da tela. */
    public const CARD_MODES = ['banner', 'fullscreen'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'style' => 'arena', 'animation' => 'slide', 'seconds' => 7, 'kinds' => self::KINDS, 'score' => true,
            'position' => 'left', 'x' => null, 'y' => null, 'text_scale' => 100, 'card_mode' => 'banner',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function normalize(?array $settings): array
    {
        $values = array_merge(self::defaults(), array_intersect_key($settings ?? [], self::defaults()));
        $seconds = is_numeric($values['seconds']) ? (int) $values['seconds'] : self::defaults()['seconds'];
        $free = is_numeric($values['x'] ?? null) && is_numeric($values['y'] ?? null);
        $kinds = is_array($values['kinds']) ? array_values(array_intersect(self::KINDS, array_map('strval', $values['kinds']))) : self::KINDS;

        return [
            'style' => in_array($values['style'], self::STYLES, true) ? (string) $values['style'] : 'arena',
            'animation' => in_array($values['animation'], self::ANIMATIONS, true) ? (string) $values['animation'] : 'slide',
            'seconds' => min(15, max(3, $seconds)),
            'kinds' => $kinds,
            'score' => (bool) $values['score'],
            'position' => in_array($values['position'], self::POSITIONS, true) ? (string) $values['position'] : 'left',
            // Posição livre em % da tela: só vale com os dois.
            'x' => $free ? self::percent($values['x']) : null,
            'y' => $free ? self::percent($values['y']) : null,
            'text_scale' => is_numeric($values['text_scale']) ? min(200, max(50, (int) round((float) $values['text_scale']))) : 100,
            'card_mode' => in_array($values['card_mode'], self::CARD_MODES, true) ? (string) $values['card_mode'] : 'banner',
        ];
    }

    private static function percent(mixed $value): int
    {
        return min(100, max(0, (int) round((float) $value)));
    }
}

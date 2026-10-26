<?php

namespace App\Modules\Broadcast\Support;

/** Regras opcionais para colocar cartelas de pré-jogo e encerramento no ar sem intervenção manual. */
final class OverlayAutomationSettings
{
    /** @return array{enabled: bool, pregame: bool, summary: bool} */
    public static function normalize(?array $settings): array
    {
        return [
            'enabled' => ($settings['enabled'] ?? false) === true,
            'pregame' => ($settings['pregame'] ?? true) === true,
            'summary' => ($settings['summary'] ?? true) === true,
        ];
    }
}

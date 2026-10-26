<?php

use App\Modules\Broadcast\Support\OverlayAutomationSettings;

it('mantém a automação desligada por padrão e normaliza as opções', function (): void {
    expect(OverlayAutomationSettings::normalize([]))->toBe(['enabled' => false, 'pregame' => true, 'summary' => true])
        ->and(OverlayAutomationSettings::normalize(['enabled' => true, 'pregame' => false, 'summary' => 'sim']))
        ->toBe(['enabled' => true, 'pregame' => false, 'summary' => false]);
});

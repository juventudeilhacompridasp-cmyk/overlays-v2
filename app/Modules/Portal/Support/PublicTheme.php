<?php

namespace App\Modules\Portal\Support;

use App\Modules\Tenancy\CurrentOrganization;

/** Identidade visual do portal público, vinda das configurações da organização (`settings.theme`). */
final class PublicTheme
{
    public const DEFAULT_COLOR = '#059669';

    /** @return array{name: string, primary: string, tagline: string|null} */
    public static function current(): array
    {
        $organization = CurrentOrganization::get();
        $theme = $organization?->settings['theme'] ?? [];
        $primary = $theme['primary'] ?? null;

        return [
            'name' => $organization?->name ?? config('app.name'),
            'primary' => is_string($primary) && preg_match('/^#[0-9a-fA-F]{6}$/', $primary) === 1 ? $primary : self::DEFAULT_COLOR,
            'tagline' => isset($theme['tagline']) && is_string($theme['tagline']) && $theme['tagline'] !== '' ? $theme['tagline'] : null,
        ];
    }
}

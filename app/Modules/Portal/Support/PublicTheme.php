<?php

namespace App\Modules\Portal\Support;

use App\Modules\Tenancy\Actions\ChangeOrganizationLogo;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;

/** Identidade visual do portal público, vinda das configurações da organização (`settings.theme`). */
final class PublicTheme
{
    public const DEFAULT_COLOR = '#059669';

    /** Cor padrão da instalação (`PORTAL_DEFAULT_COLOR`); valor inválido volta à cor original. */
    public static function defaultColor(): string
    {
        $color = config('platform.default_color');

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : self::DEFAULT_COLOR;
    }

    /** @return array{name: string, primary: string, tagline: string|null, logo_url: string|null, white_label: bool, platform_name: string} */
    public static function current(): array
    {
        $organization = CurrentOrganization::get();
        $theme = $organization?->settings['theme'] ?? [];
        $primary = $theme['primary'] ?? null;

        $whiteLabel = CurrentOrganization::has(Feature::WhiteLabel);

        return [
            'name' => $organization === null ? (string) config('app.name') : $organization->name,
            'primary' => is_string($primary) && preg_match('/^#[0-9a-fA-F]{6}$/', $primary) === 1 ? $primary : self::defaultColor(),
            'logo_url' => $whiteLabel && $organization instanceof Organization ? ChangeOrganizationLogo::url($organization, 128) : null,
            'white_label' => $whiteLabel,
            'platform_name' => (string) config('app.name'),
            'tagline' => isset($theme['tagline']) && is_string($theme['tagline']) && $theme['tagline'] !== '' ? $theme['tagline'] : null,
        ];
    }
}

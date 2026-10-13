<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlayChannel;
use Illuminate\Validation\Rule;

final class OverlayControlRules
{
    /** @return array<string, list<mixed>> */
    public static function for(OverlayChannel $channel): array
    {
        return [
            'game_id' => ['sometimes', 'nullable', 'string', Rule::exists('games', 'id')->where('organization_id', $channel->organization_id)],
            'layers' => ['sometimes', 'array'],
            'layers.*' => ['boolean'],
            'appearance' => ['sometimes', 'array'],
            'appearance.preset' => ['sometimes', 'string', Rule::in(OverlayAppearance::PRESETS)],
            'appearance.accent_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'appearance.background_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'appearance.text_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'appearance.position' => ['sometimes', 'string', Rule::in(OverlayAppearance::POSITIONS)],
            'appearance.scale' => ['sometimes', 'numeric', 'between:0.75,1.5'],
            'appearance.motion' => ['sometimes', 'string', Rule::in(OverlayAppearance::MOTIONS)],
        ];
    }
}


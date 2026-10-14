<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayFont;
use App\Modules\Matches\Enums\LineupSlot;
use Illuminate\Validation\Rule;

final class OverlayControlRules
{
    /** @return array<string, list<mixed>> */
    public static function for(OverlayChannel $channel, ?string $gameId = null): array
    {
        return [
            'game_id' => ['sometimes', 'nullable', 'string', Rule::exists('games', 'id')->where('organization_id', $channel->organization_id)],
            'scene' => ['sometimes', 'string', Rule::exists('overlay_scenes', 'id')->where('organization_id', $channel->organization_id)],
            'layers' => ['sometimes', 'array'],
            'layers.*' => ['boolean'],
            'championship_live' => ['sometimes', 'array'],
            'championship_live.*' => ['boolean'],
            'appearance' => ['sometimes', 'array'],
            'appearance.preset' => ['sometimes', 'string', Rule::in(OverlayAppearance::PRESETS)],
            'appearance.accent_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'appearance.background_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'appearance.text_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'appearance.position' => ['sometimes', 'string', Rule::in(OverlayAppearance::POSITIONS)],
            'appearance.scale' => ['sometimes', 'numeric', 'between:0.75,1.5'],
            'appearance.layout' => ['sometimes', 'string', Rule::in(OverlayAppearance::LAYOUTS)],
            'appearance.motion' => ['sometimes', 'string', Rule::in(OverlayAppearance::MOTIONS)],
            'appearance.font' => ['sometimes', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($channel): void {
                $known = in_array($value, OverlayAppearance::FONTS, true)
                    || OverlayFont::query()->where('organization_id', $channel->organization_id)->whereKey(is_string($value) ? strtolower($value) : '')->exists();

                if (! $known) {
                    $fail('A fonte escolhida não existe.');
                }
            }],
            'sponsor_settings' => ['sometimes', 'array'],
            'sponsor_settings.enabled' => ['sometimes', 'boolean'],
            'sponsor_settings.seconds' => ['sometimes', 'integer', 'between:3,30'],
            'lineup_settings' => ['sometimes', 'array'],
            'lineup_settings.presentation' => ['sometimes', 'string', Rule::in(OverlayLineupSettings::PRESENTATIONS)],
            'lineup_settings.list_seconds' => ['sometimes', 'integer', 'between:3,20'],
            'lineup_settings.team' => ['sometimes', 'string', Rule::in(OverlayLineupSettings::TEAMS)],
            'lineup_settings.individual_mode' => ['sometimes', 'string', Rule::in(OverlayLineupSettings::INDIVIDUAL_MODES)],
            'lineup_settings.individual_seconds' => ['sometimes', 'integer', 'between:3,30'],
            'lineup_settings.individual_scope' => ['sometimes', 'string', Rule::in(OverlayLineupSettings::SCOPES)],
            'lineup_settings.sponsor_banner' => ['sometimes', 'boolean'],
            'lineup_command' => ['sometimes', 'string', Rule::in(OverlayLineupSettings::COMMANDS)],
            'lineup_settings.photo_person_ids' => ['sometimes', 'array', 'max:40'],
            'lineup_settings.photo_person_ids.*' => [
                'string',
                'distinct',
                Rule::exists('game_lineups', 'person_id')->where(fn ($query) => $query
                    ->where('organization_id', $channel->organization_id)
                    ->where('game_id', $gameId ?? '')
                    ->whereIn('slot', [LineupSlot::Starter->value, LineupSlot::Bench->value])),
            ],
        ];
    }
}

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
            'stoppage_minutes' => ['sometimes', 'integer', 'between:0,15'],
            'screens_live' => ['sometimes', 'array'],
            'screens_live.*' => ['boolean'],
            'screen_settings' => ['sometimes', 'array'],
            'screen_settings.title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'screen_settings.subtitle' => ['sometimes', 'nullable', 'string', 'max:80'],
            'screen_settings.minutes' => ['sometimes', 'nullable', 'integer', 'between:0,180'],
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
            'appearance.scorers' => ['sometimes', 'boolean'],
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
            'lower_third_show' => ['sometimes', 'array'],
            'lower_third_show.kind' => ['required_with:lower_third_show', 'string', Rule::in(['custom', 'player', ...OverlayLowerThirdSettings::KINDS])],
            'lower_third_show.title' => ['required_if:lower_third_show.kind,custom', 'nullable', 'string', 'max:60'],
            'lower_third_show.subtitle' => ['nullable', 'string', 'max:80'],
            'lower_third_show.person_id' => ['required_if:lower_third_show.kind,player', 'nullable', 'string', 'max:40'],
            'lower_third_settings' => ['sometimes', 'array'],
            'lower_third_settings.style' => ['sometimes', 'string', Rule::in(OverlayLowerThirdSettings::STYLES)],
            'lower_third_settings.animation' => ['sometimes', 'string', Rule::in(OverlayLowerThirdSettings::ANIMATIONS)],
            'lower_third_settings.seconds' => ['sometimes', 'integer', 'between:3,15'],
            'lower_third_settings.score' => ['sometimes', 'boolean'],
            'lower_third_settings.kinds' => ['sometimes', 'array'],
            'lower_third_settings.kinds.*' => ['string', Rule::in(OverlayLowerThirdSettings::KINDS)],
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

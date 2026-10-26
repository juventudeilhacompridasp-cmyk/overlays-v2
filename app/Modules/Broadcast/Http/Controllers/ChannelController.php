<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\UpdateOverlayChannel;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayCustomItem;
use App\Modules\Broadcast\Models\OverlayFont;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Models\OverlaySponsor;
use App\Modules\Broadcast\Models\OverlayStyleModel;
use App\Modules\Broadcast\Support\ChannelPayload;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayAutomationSettings;
use App\Modules\Broadcast\Support\OverlayControlRules;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlayLowerThirdSettings;
use App\Modules\Broadcast\Support\OverlayReportSettings;
use App\Modules\Broadcast\Support\OverlaySponsorSettings;
use App\Modules\Broadcast\Support\OverlayStatsSettings;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Models\Game;
use App\Modules\Reports\Enums\ReportKind;
use App\Modules\Reports\Support\ReportSections;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Painel "Transmissão": canais do OBS, jogo no ar e camadas sob demanda. */
class ChannelController extends Controller
{
    public function index(): Response
    {
        $channels = OverlayChannel::query()->with([
            'game.homeEntry.team',
            'game.awayEntry.team',
            'game.lineups.person',
        ])->orderBy('name')->get();

        $customItems = OverlayCustomItem::query()->orderBy('name')->get();

        $label = ChannelPayload::gameLabel(...);
        $candidates = ChannelPayload::candidateGames();

        return Inertia::render('Panel/Broadcast/Index', [
            'channels' => $channels->map(fn (OverlayChannel $channel): array => ChannelPayload::for($channel, $customItems, $label))->values(),
            'sponsors' => OverlaySponsor::query()->orderBy('sort')->orderBy('created_at')->get()->map(fn (OverlaySponsor $sponsor) => [
                'id' => $sponsor->id, 'name' => $sponsor->name, 'active' => $sponsor->active, 'logo_url' => $sponsor->logoUrl(128), 'banner_url' => $sponsor->bannerUrl(),
            ])->values(),
            'sponsor_limit' => OverlaySponsor::LIMIT,
            'custom_items' => $customItems->map(fn (OverlayCustomItem $item) => ['id' => $item->getKey(), 'name' => $item->name, 'width' => $item->width, 'height' => $item->height, 'elements' => $item->elements])->values(),
            'custom_limit' => OverlayCustomItem::LIMIT,
            'defaults' => [
                'appearance' => OverlayAppearance::normalize([]),
                'lower_third_settings' => OverlayLowerThirdSettings::normalize([]),
                'lineup_settings' => array_diff_key(OverlayLineupSettings::normalize([]), ['photo_person_ids' => 0]),
                'sponsor_settings' => OverlaySponsorSettings::normalize([]),
                'stats_settings' => OverlayStatsSettings::normalize([]),
                'automation_settings' => OverlayAutomationSettings::normalize([]),
                'report_settings' => OverlayReportSettings::normalize([]),
            ],
            'report_sections' => ['game' => ReportSections::options(ReportKind::Game), 'championship' => ReportSections::options(ReportKind::Championship)],
            'fonts' => OverlayFont::query()->orderBy('name')->get()->map(fn (OverlayFont $font) => ['id' => $font->id, 'name' => $font->name, 'format' => $font->format])->values(),
            'font_limit' => OverlayFont::LIMIT,
            'scenes' => OverlayScene::query()->orderBy('name')->get()->map(fn (OverlayScene $scene) => ['id' => $scene->id, 'name' => $scene->name])->values(),
            'scene_limit' => OverlayScene::LIMIT,
            'style_models' => OverlayStyleModel::query()->orderBy('name')->get()->map(fn (OverlayStyleModel $model) => ['id' => $model->id, 'name' => $model->name, 'options' => $model->options])->values(),
            'style_model_limit' => OverlayStyleModel::LIMIT,
            'championship_seasons' => Season::query()->published()->with(['competition', 'divisions' => fn ($query) => $query->orderBy('name')])
                ->orderByDesc('year')->orderByDesc('created_at')->limit(40)->get()
                ->map(fn (Season $season) => [
                    'id' => $season->getKey(),
                    'label' => $season->competition->name.' · '.$season->name,
                    'divisions' => $season->divisions->map(fn ($division) => ['id' => $division->getKey(), 'name' => $division->name])->values(),
                ])->values(),
            'games' => $candidates->map(fn (Game $game) => ['id' => $game->id, 'label' => $label($game)])->values(),
            'issued_control' => session()->pull('overlay_issued_control'),
            'operator_layouts' => auth()->user()->operator_layouts ?? [],
        ]);
    }

    public function operatorLayout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'string', Rule::exists('overlay_channels', 'id')->where('organization_id', CurrentOrganization::id())],
            'layout' => ['required', 'array', 'max:12'],
            'layout.*.id' => ['required', 'string', 'max:30'],
            'layout.*.x' => ['required', 'integer', 'min:0', 'max:24'],
            'layout.*.y' => ['required', 'integer', 'min:0', 'max:16'],
            'layout.*.w' => ['required', 'integer', 'min:1', 'max:24'],
            'layout.*.h' => ['required', 'integer', 'min:1', 'max:16'],
        ]);

        $user = $request->user();
        $layouts = (array) $user->operator_layouts;
        $layouts[$validated['channel_id']] = array_values($validated['layout']);
        $user->forceFill(['operator_layouts' => $layouts])->save();

        return back()->with('success', 'Layout do modo operador salvo.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('overlay_channels', 'name')->where('organization_id', CurrentOrganization::id())],
        ], [], ['name' => 'nome']);

        $controlToken = OverlayChannel::newControlToken();
        $channel = OverlayChannel::query()->create([
            'name' => $validated['name'],
            'token' => OverlayChannel::newToken(),
            'control_token_hash' => OverlayChannel::controlTokenHash($controlToken),
            'layers' => [],
            'automation_settings' => OverlayAutomationSettings::normalize([]),
            'appearance' => OverlayAppearance::defaults(),
            'lineup_settings' => OverlayLineupSettings::defaults(),
            'sponsor_settings' => OverlaySponsorSettings::defaults(),
        ]);

        return back()
            ->with('success', 'Canal criado. Copie as URLs para o OBS e guarde a chave da API.')
            ->with('overlay_issued_control', ['channel_id' => $channel->id, 'token' => $controlToken]);
    }

    /** Operação ao vivo: escolhe o jogo no ar e liga/desliga camadas. */
    public function update(Request $request, OverlayChannel $channel, UpdateOverlayChannel $update): RedirectResponse
    {
        $gameId = $request->exists('game_id')
            ? (is_string($request->input('game_id')) ? $request->input('game_id') : null)
            : $channel->game_id;

        $update->handle($channel, $request->validate(OverlayControlRules::for($channel, $gameId)));

        return back()->with('success', 'Transmissão atualizada.');
    }

    /** Invalida as URLs antigas (ex.: se o link vazou). As fontes do OBS precisam ser atualizadas. */
    public function regenerate(OverlayChannel $channel): RedirectResponse
    {
        $channel->update(['token' => OverlayChannel::newToken()]);

        return back()->with('success', 'Token renovado. Atualize as URLs no OBS.');
    }

    /** Emite uma nova chave de escrita; o valor em claro aparece somente nesta resposta. */
    public function regenerateControlToken(OverlayChannel $channel): RedirectResponse
    {
        $controlToken = OverlayChannel::newControlToken();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($controlToken)]);

        return back()
            ->with('success', 'Chave da API renovada. A chave anterior deixou de funcionar.')
            ->with('overlay_issued_control', ['channel_id' => $channel->id, 'token' => $controlToken]);
    }

    public function destroy(OverlayChannel $channel): RedirectResponse
    {
        $channel->delete();

        return back()->with('success', 'Canal excluído.');
    }
}

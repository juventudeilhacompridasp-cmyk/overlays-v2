<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Actions\CloseGameSheet;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Models\ActivityLog;
use App\Modules\Tenancy\Support\MesarioSettings;
use App\Modules\Tenancy\Support\OperationSettings;
use App\Modules\Tenancy\Support\SheetClosingSettings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel do operador da partida (mesário): numa tela só, o estado do jogo, os atalhos de quem opera (súmula, resultado rápido,
 * transmissão, PDFs), quem está escalado, a mesa/arbitragem, os últimos lances e as alterações recentes (registro de atividades).
 * Consultar vale para qualquer membro; quem comanda é a súmula (`operate-games`). Nada de CPF, documento ou contato.
 */
class GameOperatorController extends Controller
{
    public function show(Game $game): Response
    {
        $game->load(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage', 'officials.official', 'tactics']);
        $organization = CurrentOrganization::get();
        $history = MesarioSettings::all()['history'];
        $user = auth()->user();

        $teams = [
            'home' => $this->team($game, (string) $game->home_entry_id, $game->homeEntry->team->name),
            'away' => $this->team($game, (string) $game->away_entry_id, $game->awayEntry->team->name),
        ];

        $events = GameEvent::query()->effective()->where('game_id', $game->getKey())->with(['person', 'entry.team'])
            ->latest('created_at')->latest('id')->limit(12)->get()
            ->map(fn (GameEvent $event) => [
                'id' => $event->getKey(),
                'label' => $event->type->label(),
                'minute' => $event->minute,
                'period' => $event->period,
                'team' => $event->entry?->team?->name,
                'person' => $event->person?->name,
            ])->values();

        return Inertia::render('Panel/Games/Operator', [
            'game' => [
                'id' => $game->getKey(),
                'home' => $teams['home'],
                'away' => $teams['away'],
                'home_score' => $game->home_score,
                'away_score' => $game->away_score,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'open' => ! $game->status->isDecided(),
                'live' => $game->status === GameStatus::InProgress,
                'scheduled_at' => $game->scheduled_at?->toIso8601String(),
                'venue' => $game->venue?->name,
                'competition' => "{$game->division->season->competition->name} · {$game->division->name}",
                'stage' => $game->stage->name,
                'knockout' => $game->stage->type === StageType::Knockout,
                'quick_result' => OperationSettings::quickResultAllowed() && $game->division->ruleSet()->allowQuickResult && $game->sport()->profile()->scoreModel !== 'sets' && $game->stage->type !== StageType::Knockout,
            ],
            'closure' => $this->closure($game),
            'officials' => $game->officials->map(fn ($assignment) => ['role' => $assignment->role->label(), 'name' => $assignment->official->name])->values(),
            'events' => $events,
            'history' => $history > 0 ? ActivityLog::query()->where('game_id', $game->getKey())->latest('created_at')->latest('id')->limit($history)->get()
                ->map(fn (ActivityLog $entry) => [
                    'id' => $entry->getKey(),
                    'at' => $entry->created_at->toIso8601String(),
                    'actor' => $entry->actor_name,
                    'role' => $entry->actor_role,
                    'summary' => $entry->summary,
                    'outcome' => $entry->outcome,
                    'changes' => $entry->changes ?? [],
                ])->values() : [],
            'links' => [
                'remote' => $organization?->hasFeature(Feature::Overlays) ? route('panel.broadcast.remote') : null,
                'broadcast' => $organization?->hasFeature(Feature::Overlays) ? route('panel.broadcast.index') : null,
                'reports' => $organization?->hasFeature(Feature::Reports),
                'activity' => $user instanceof User && $user->hasPermission(Permission::UsersManage) ? route('panel.activity-log.index', ['game' => $game->getKey()]) : null,
            ],
        ]);
    }

    /** @return array{closed: bool, can_close: bool, by: ?string, at: ?string, signatures: int, required: int, notes: ?string} */
    private function closure(Game $game): array
    {
        $closure = $game->sheetClosure;

        return [
            'closed' => $closure !== null,
            'can_close' => $closure === null && $game->status->isDecided(),
            'by' => $closure?->closed_by_name,
            'at' => $closure?->closed_at?->toIso8601String(),
            'signatures' => $closure !== null ? $closure->signatures_count : CloseGameSheet::signedCount($game),
            'required' => SheetClosingSettings::all()['min_signatures'],
            'notes' => $closure?->notes,
        ];
    }

    /** @return array{name: string, starters: int, bench: int, confirmed: bool} */
    private function team(Game $game, string $entryId, string $name): array
    {
        $lineups = $game->lineups()->where('entry_id', $entryId)->get(['slot']);

        return [
            'name' => $name,
            'starters' => $lineups->where('slot', LineupSlot::Starter)->count(),
            'bench' => $lineups->where('slot', LineupSlot::Bench)->count(),
            'confirmed' => $game->tactics->firstWhere('entry_id', $entryId)?->confirmed_at !== null,
        ];
    }
}

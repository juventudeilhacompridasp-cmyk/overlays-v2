<?php

namespace App\Modules\Matches\Support;

use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\Official;
use App\Modules\Matches\Scheduling\GameConflicts;
use App\Modules\Tenancy\Support\OperationalAlertSettings;
use Illuminate\Support\Collection;

/**
 * Varredura de problemas operacionais dos jogos que estão perto: sem escalação de uma das equipes, sem árbitro escalado, sem
 * estádio e conflitos de agenda (estádio ou descanso da equipe, pelos tempos do regulamento da divisão). Só lê e devolve
 * textos; quem decide o que fazer é a organização. As verificações e a janela vêm de `OperationalAlertSettings`.
 * Não mexe em dados pessoais: só nomes de equipes, estádios e horários.
 */
final class OperationalAlerts
{
    /**
     * @return list<array{game_id: string, label: string, at: string, problems: list<array{kind: string, key: string, text: string}>}>
     */
    public function scan(): array
    {
        $settings = OperationalAlertSettings::all();
        $checks = $settings['checks'];
        $from = now();
        $to = now()->addHours($settings['hours_ahead']);

        $games = Game::query()
            ->where('status', GameStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')->whereBetween('scheduled_at', [$from, $to])
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->whereHas('division.season', fn ($season) => $season->where('status', '!=', SeasonStatus::Draft->value))
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division', 'lineups', 'officials'])
            ->orderBy('scheduled_at')->limit(OperationalAlertSettings::maxGames())->get();

        $hasOfficials = $checks['officials'] && Official::query()->where('active', true)->exists();
        $conflicts = $checks['conflicts'] ? $this->conflicts($games) : [];

        $alerts = [];

        foreach ($games as $game) {
            $problems = [];
            $home = $game->homeEntry->team->name;
            $away = $game->awayEntry->team->name;

            if ($checks['lineup']) {
                foreach ([[$game->home_entry_id, $home], [$game->away_entry_id, $away]] as [$entryId, $name]) {
                    if ($game->lineups->where('entry_id', $entryId)->where('slot', LineupSlot::Starter)->isEmpty()) {
                        $problems[] = ['kind' => 'lineup', 'key' => "lineup:{$entryId}", 'text' => "{$name} sem escalação"];
                    }
                }
            }

            if ($hasOfficials && $game->officials->isEmpty()) {
                $problems[] = ['kind' => 'officials', 'key' => 'officials', 'text' => 'Sem árbitro ou mesário escalado'];
            }

            if ($checks['venue'] && $game->venue_id === null) {
                $problems[] = ['kind' => 'venue', 'key' => 'venue', 'text' => 'Sem estádio definido'];
            }

            foreach ($conflicts[$game->getKey()] ?? [] as $conflict) {
                $problems[] = [
                    'kind' => 'conflicts',
                    'key' => "conflict:{$conflict['kind']}:{$conflict['with']}",
                    'text' => ($conflict['kind'] === 'venue' ? 'Conflito de estádio com ' : 'Equipe com pouco descanso entre ').$conflict['label'],
                ];
            }

            if ($problems !== []) {
                $alerts[] = ['game_id' => (string) $game->getKey(), 'label' => "{$home} x {$away}", 'at' => $game->scheduled_at->toIso8601String(), 'problems' => $problems];
            }
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, Game>  $focus
     * @return array<string, list<array{kind: 'venue'|'team', with: string, label: string}>>
     */
    private function conflicts(Collection $focus): array
    {
        if ($focus->isEmpty()) {
            return [];
        }

        $others = Game::query()->where('status', GameStatus::Scheduled->value)->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$focus->min('scheduled_at')->copy()->subDay(), $focus->max('scheduled_at')->copy()->addDay()])
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->with(['homeEntry.team', 'awayEntry.team', 'division'])->get();

        $found = [];

        foreach ($focus->groupBy('division_id') as $divisionGames) {
            $rules = $divisionGames->first()->division->ruleSet();
            $rows = $others->map(fn (Game $game) => [
                'id' => (string) $game->getKey(),
                'at' => $game->scheduled_at->getTimestamp(),
                'venue' => $game->venue_id === null ? null : (string) $game->venue_id,
                'teams' => [(string) $game->homeEntry->team_id, (string) $game->awayEntry->team_id],
                'label' => "{$game->homeEntry->team->name} x {$game->awayEntry->team->name}",
            ])->values()->all();

            $found += GameConflicts::find($rows, $divisionGames->map(fn (Game $game) => (string) $game->getKey())->all(), $rules->venueConflictMinutes, $rules->teamRestMinutes);
        }

        return $found;
    }
}

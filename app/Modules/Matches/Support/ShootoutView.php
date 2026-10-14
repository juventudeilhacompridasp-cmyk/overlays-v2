<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Rules\Shootout\ShootoutState;

/**
 * A disputa de pênaltis de um jogo: cobranças de cada equipe (convertida ou perdida), quem cobra a seguir e se já está decidida.
 * Só números e lados: nunca o cobrador (a súmula lança as cobranças de forma anônima). Nulo quando não há disputa.
 */
final class ShootoutView
{
    /**
     * @return array{home: list<bool>, away: list<bool>, goals: array{home: int, away: int}, first: string|null, next: string|null, decided: bool, winner: string|null, rounds: int, by_kick: bool}|null
     */
    public static function for(Game $game): ?array
    {
        $events = $game->events()->effective()->where('type', GameEventType::PenaltyKick)->orderBy('id')->get();

        if ($events->isEmpty()) {
            return null;
        }

        $side = fn (?string $entryId): ?string => $entryId === $game->home_entry_id ? 'home' : ($entryId === $game->away_entry_id ? 'away' : null);
        $kicks = [];

        foreach ($events as $event) {
            $who = $side($event->entry_id);

            if ($who !== null) {
                $kicks[] = ['side' => $who, 'scored' => ($event->payload['scored'] ?? false) === true];
            }
        }

        $byKick = $events->every(fn ($event) => isset($event->payload['kick']));
        $state = ShootoutState::from($kicks);

        // Total lançado à mão (sem a ordem das cobranças): vale o placar que a súmula guardou.
        if (! $byKick) {
            $state['next'] = null;
            $state['decided'] = $state['goals']['home'] !== $state['goals']['away'];
            $state['winner'] = $state['decided'] ? ($state['goals']['home'] > $state['goals']['away'] ? 'home' : 'away') : null;
        }

        return $state + ['by_kick' => $byKick];
    }
}

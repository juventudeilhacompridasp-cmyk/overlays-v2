<?php

namespace App\Modules\Teams\Support;

use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;

/**
 * Avisos por e-mail ao gestor disparados por mudanças no jogo (ADR 0003): horário/local alterados, jogo adiado ou
 * cancelado e suspensões geradas por um jogo encerrado. Só dados do próprio jogo e da própria equipe.
 */
final class GameNotices
{
    public static function afterUpdate(Game $game): void
    {
        $organization = Organization::query()->find($game->organization_id);

        if ($organization === null || ! $organization->hasFeature(Feature::TeamPortal)) {
            return;
        }

        CurrentOrganization::using($organization, function () use ($organization, $game): void {
            $game->loadMissing(['homeEntry.team', 'awayEntry.team', 'venue']);

            if (self::changed($game)) {
                self::notifyChange($organization, $game);
            }

            if ($game->wasChanged('status') && $game->status->isDecided()) {
                foreach ([$game->homeEntry, $game->awayEntry] as $entry) {
                    if ($entry !== null) {
                        self::notifySuspensions($organization, $game, $entry);
                    }
                }
            }
        });
    }

    private static function changed(Game $game): bool
    {
        if ($game->wasChanged('status')) {
            return in_array($game->status, [GameStatus::Postponed, GameStatus::Cancelled, GameStatus::Suspended], true);
        }

        return $game->wasChanged(['scheduled_at', 'venue_id']) && in_array($game->status, [GameStatus::Scheduled, GameStatus::Postponed], true);
    }

    private static function notifyChange(Organization $organization, Game $game): void
    {
        $when = $game->scheduled_at?->format('d/m/Y H:i') ?? 'a definir';
        $where = $game->venue?->name ?? 'a definir';
        $state = md5($when.'|'.$where.'|'.$game->status->value);

        foreach ([[$game->homeEntry, $game->awayEntry], [$game->awayEntry, $game->homeEntry]] as [$mine, $other]) {
            if ($mine === null) {
                continue;
            }

            $versus = "{$mine->team->name} x ".($other?->team->name ?? 'a definir');

            ManagerNotifier::send(
                $organization,
                $mine->team,
                'game_changed',
                "changed:{$game->getKey()}:{$state}",
                "Jogo atualizado: {$versus}",
                $game->status === GameStatus::Scheduled ? 'A data ou o local do jogo mudou.' : "A situação do jogo mudou para {$game->status->label()}.",
                ["Jogo: {$versus}", "Quando: {$when}", "Local: {$where}", "Situação: {$game->status->label()}"],
                "/gestor/jogos/{$game->getKey()}",
            );
        }
    }

    private static function notifySuspensions(Organization $organization, Game $game, Entry $entry): void
    {
        $carded = GameEvent::query()->effective()
            ->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())
            ->whereIn('type', [GameEventType::YellowCard, GameEventType::RedCard])
            ->whereNotNull('person_id')->pluck('person_id')->unique();

        if ($carded->isEmpty()) {
            return;
        }

        $suspended = array_filter(app(CalculateDiscipline::class)->forEntry($entry)->suspended(), fn (int $games) => $games > 0);
        $names = Person::query()->whereIn('id', $carded->filter(fn ($id) => isset($suspended[$id]))->all())->get();

        if ($names->isEmpty()) {
            return;
        }

        ManagerNotifier::send(
            $organization,
            $entry->team,
            'suspensions',
            "suspended:{$game->getKey()}:{$entry->getKey()}",
            "Suspensões em {$entry->team->name}",
            'Depois do último jogo, há atletas suspensos para o próximo jogo da equipe.',
            $names->map(fn (Person $person) => ($person->nickname ?: $person->name).' — suspenso por '.$suspended[$person->getKey()].' jogo(s)')->values()->all(),
            "/gestor/inscricoes/{$entry->getKey()}",
        );
    }
}

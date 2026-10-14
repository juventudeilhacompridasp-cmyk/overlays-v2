<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Portal\Support\PortalSettings;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Teams\Models\Person;

/**
 * Resumo do jogo no ar (intervalo ou fim): título conforme a situação, cartões (só se a organização ligou `game_cards`) e o
 * "craque do jogo" escolhido pelo operador. Os gols vêm do retrato público do jogo. Nomes por {@see PublicName}; sem IDs,
 * documentos, nascimento ou fotos.
 */
final class OverlaySummary
{
    /**
     * @return array{headline: string, cards: list<array{side: string, kind: string, player: string|null, minute: int|null}>|null, mvp: array{name: string, shirt: int|null, team: string|null, side: string|null}|null}
     */
    public static function for(Game $game, ?string $mvpPersonId): array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);

        return [
            'headline' => match (true) {
                $game->status->isDecided() => 'FIM DE JOGO',
                $game->status === GameStatus::InProgress => 'INTERVALO',
                default => 'RESUMO',
            },
            'cards' => PortalSettings::enabled('game_cards') ? self::cards($game) : null,
            'mvp' => $mvpPersonId === null ? null : self::mvp($game, $mvpPersonId),
        ];
    }

    /** @return list<array{side: string, kind: string, player: string|null, minute: int|null}> */
    private static function cards(Game $game): array
    {
        $events = $game->events()->effective()->whereIn('type', [GameEventType::YellowCard, GameEventType::RedCard])->orderBy('period')->orderBy('minute')->get();
        $people = Person::query()->whereIn('id', $events->pluck('person_id')->filter())->get()->keyBy('id');

        return $events->map(fn (GameEvent $event): array => [
            'side' => $event->entry_id === $game->home_entry_id ? 'home' : 'away',
            'kind' => $event->type->value,
            'player' => PublicName::of($people->get($event->person_id)),
            'minute' => $event->minute,
        ])->values()->all();
    }

    /** @return array{name: string, shirt: int|null, team: string|null, side: string|null}|null */
    private static function mvp(Game $game, string $personId): ?array
    {
        $row = GameLineup::query()->where('game_id', $game->getKey())->where('person_id', $personId)->whereIn('slot', [LineupSlot::Starter, LineupSlot::Bench])->first();
        $person = $row === null ? null : Person::query()->find($personId);

        if ($row === null || $person === null) {
            return null;
        }

        $home = $row->entry_id === $game->home_entry_id;

        return ['name' => PublicName::of($person) ?? '—', 'shirt' => $row->shirt_number, 'team' => ($home ? $game->homeEntry : $game->awayEntry)?->team->name, 'side' => $home ? 'home' : 'away'];
    }
}

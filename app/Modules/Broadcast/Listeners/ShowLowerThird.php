<?php

namespace App\Modules\Broadcast\Listeners;

use App\Modules\Broadcast\Events\OverlayLowerThird;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Events\GameEventRecorded;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Models\Person;
use Illuminate\Support\Str;

/** Gol, cartão ou substituição lançado na súmula vira uma faixa na tela de eventos dos canais com este jogo no ar. */
class ShowLowerThird
{
    public function handle(GameEventRecorded $recorded): void
    {
        $channels = OverlayChannel::query()->where('game_id', $recorded->gameId)->get();

        if ($channels->isEmpty()) {
            return;
        }

        $event = GameEvent::query()->find($recorded->eventId);
        $game = Game::query()->with(['homeEntry.team', 'awayEntry.team'])->find($recorded->gameId);

        if ($event === null || $game === null) {
            return;
        }

        $title = match ($event->type) {
            GameEventType::Goal => 'GOL!',
            GameEventType::OwnGoal => 'GOL CONTRA',
            GameEventType::YellowCard => 'CARTÃO AMARELO',
            GameEventType::RedCard => 'CARTÃO VERMELHO',
            GameEventType::BlueCard => 'CARTÃO AZUL',
            GameEventType::Substitution => 'SUBSTITUIÇÃO',
            default => null,
        };

        if ($title === null) {
            return;
        }

        $team = $event->entry_id === $game->home_entry_id ? $game->homeEntry?->team : $game->awayEntry?->team;
        $name = function (?string $id): ?string {
            $person = $id === null ? null : Person::query()->find($id, ['id', 'name', 'nickname']);

            return $person === null ? null : ($person->nickname ?: $person->name);
        };

        $subtitle = $event->type === GameEventType::Substitution
            ? trim('↑ '.$name($event->secondary_person_id).'   ↓ '.$name($event->person_id))
            : $name($event->person_id);

        $banner = [
            'id' => (string) Str::uuid(),
            'kind' => $event->type->value,
            'title' => $title,
            'subtitle' => $subtitle,
            'team' => $team?->name,
            'color' => $team?->primary_color,
            'minute' => $event->minute,
        ];

        $channels->each(fn (OverlayChannel $channel) => event(new OverlayLowerThird($channel->token, $banner)));
    }
}

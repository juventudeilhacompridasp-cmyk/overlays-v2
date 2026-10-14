<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Rules\Tactics\Formations;
use App\Modules\Teams\Models\Person;
use Illuminate\Support\Str;

/**
 * Monta a faixa (lower third) que a tela de eventos desenha: lance da súmula, aviso manual ou jogador em destaque.
 * Nomes de atletas sempre por {@see PublicName}; nunca documentos, nascimento ou foto. O placar só entra nos gols.
 *
 * @phpstan-type Banner array{id: string, kind: string, title: string, subtitle: string|null, team: string|null, color: string|null, minute: int|null, team_short: string|null, team_logo: string|null, player: array{name: string, shirt: int|null, position: string|null}|null, score: array{home: int, away: int, home_short: string, away_short: string}|null, detail: string|null}
 */
final class LowerThirdBanner
{
    public const TITLES = [
        'goal' => 'GOL!',
        'own_goal' => 'GOL CONTRA',
        'yellow_card' => 'CARTÃO AMARELO',
        'red_card' => 'CARTÃO VERMELHO',
        'blue_card' => 'CARTÃO AZUL',
        'substitution' => 'SUBSTITUIÇÃO',
    ];

    /**
     * Faixa de um lance da súmula; null para os tipos que não viram faixa.
     *
     * @return Banner|null
     */
    public static function forEvent(GameEvent $event, Game $game, bool $withScore = true): ?array
    {
        $title = self::TITLES[$event->type->value] ?? null;

        if ($title === null) {
            return null;
        }

        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);
        $entry = $event->entry_id === $game->home_entry_id ? $game->homeEntry : $game->awayEntry;
        $team = $entry?->team;
        $person = $event->person_id !== null ? Person::query()->find($event->person_id) : null;
        $in = $event->secondary_person_id !== null ? Person::query()->find($event->secondary_person_id) : null;
        $name = PublicName::of($person);

        $subtitle = $event->type === GameEventType::Substitution
            ? trim('↑ '.(PublicName::of($in) ?? '').'   ↓ '.($name ?? ''))
            : $name;

        $isGoal = in_array($event->type, [GameEventType::Goal, GameEventType::OwnGoal], true);

        return self::make([
            'kind' => $event->type->value,
            'title' => $title,
            'subtitle' => $subtitle,
            'minute' => $event->minute,
            'player' => $event->type === GameEventType::Substitution ? null : self::playerInfo($game, $event->entry_id, $event->person_id, $name),
            'score' => $isGoal && $withScore ? self::score($game) : null,
            'detail' => self::detail($event),
        ], $team);
    }

    /**
     * Aviso manual (narrador, patrocinador, "intervalo"...): título e subtítulo livres, sem equipe.
     *
     * @return Banner
     */
    public static function custom(string $title, ?string $subtitle): array
    {
        return self::make(['kind' => 'custom', 'title' => $title, 'subtitle' => $subtitle], null);
    }

    /**
     * Jogador em destaque, a partir da escalação do jogo (nome público, camisa e posição).
     *
     * @return Banner|null
     */
    public static function forPlayer(Game $game, string $personId): ?array
    {
        $row = GameLineup::query()->where('game_id', $game->getKey())->where('person_id', $personId)
            ->whereIn('slot', [LineupSlot::Starter, LineupSlot::Bench])->first();

        if ($row === null) {
            return null;
        }

        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);
        $entry = $row->entry_id === $game->home_entry_id ? $game->homeEntry : $game->awayEntry;
        $person = Person::query()->find($personId);
        $name = PublicName::of($person) ?? '—';
        $player = self::playerInfo($game, $row->entry_id, $personId, $name);

        return self::make([
            'kind' => 'player',
            'title' => $name,
            'subtitle' => $player['position'] ?? null,
            'player' => $player,
        ], $entry?->team);
    }

    /**
     * Faixa de teste: usa o primeiro titular do mandante (ou um nome de exemplo) para o operador ver o estilo escolhido.
     *
     * @return Banner|null
     */
    public static function sample(Game $game, string $kind): ?array
    {
        $title = self::TITLES[$kind] ?? null;

        if ($title === null) {
            return null;
        }

        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);
        $row = GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $game->home_entry_id)->where('slot', LineupSlot::Starter)->orderBy('shirt_number')->first();
        $person = $row !== null ? Person::query()->find($row->person_id) : null;
        $name = PublicName::of($person) ?? 'Jogador Exemplo';
        $isGoal = in_array($kind, ['goal', 'own_goal'], true);

        return self::make([
            'kind' => $kind,
            'title' => $title,
            'subtitle' => $kind === 'substitution' ? '↑ Reserva   ↓ '.$name : $name,
            'minute' => 37,
            'player' => $kind === 'substitution' ? null : ['name' => $name, 'shirt' => $row->shirt_number ?? 10, 'position' => null],
            'score' => $isGoal ? self::score($game) : null,
            'detail' => $isGoal ? '1º gol do jogador na partida' : null,
        ], $game->homeEntry?->team);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Banner
     */
    private static function make(array $data, mixed $team): array
    {
        return [
            'id' => (string) Str::uuid(),
            'kind' => $data['kind'],
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'team' => $team?->name,
            'color' => $team?->primary_color,
            'minute' => $data['minute'] ?? null,
            'team_short' => $team !== null ? ($team->short_name ?: mb_strtoupper(mb_substr($team->name, 0, 3))) : null,
            'team_logo' => $team?->logoUrl(128),
            'player' => $data['player'] ?? null,
            'score' => $data['score'] ?? null,
            'detail' => $data['detail'] ?? null,
        ];
    }

    /** @return array{name: string, shirt: int|null, position: string|null}|null */
    private static function playerInfo(Game $game, ?string $entryId, ?string $personId, ?string $name): ?array
    {
        if ($personId === null || $name === null) {
            return null;
        }

        $row = GameLineup::query()->where('game_id', $game->getKey())->where('person_id', $personId)->first();
        $formation = null;

        if ($row?->position !== null && $entryId !== null) {
            $tactic = GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $entryId)->first();
            $formation = $tactic?->formation !== null ? Formations::find($game->sport()->profile()->key, $tactic->formation) : null;
        }

        return ['name' => $name, 'shirt' => $row?->shirt_number, 'position' => $formation?->slot((string) $row?->position)?->label];
    }

    /** @return array{home: int, away: int, home_short: string, away_short: string} */
    private static function score(Game $game): array
    {
        $fresh = Game::query()->with(['homeEntry.team', 'awayEntry.team'])->find($game->getKey()) ?? $game;
        $short = fn ($entry) => $entry?->team !== null ? ($entry->team->short_name ?: mb_strtoupper(mb_substr($entry->team->name, 0, 3))) : '—';

        return ['home' => (int) $fresh->home_score, 'away' => (int) $fresh->away_score, 'home_short' => $short($fresh->homeEntry), 'away_short' => $short($fresh->awayEntry)];
    }

    /** "2º gol do jogador na partida", "2º cartão amarelo" etc. */
    private static function detail(GameEvent $event): ?string
    {
        if ($event->person_id === null) {
            return null;
        }

        $count = GameEvent::query()->effective()->where('game_id', $event->game_id)->where('person_id', $event->person_id)->where('type', $event->type)->count();

        if ($count < 2) {
            return null;
        }

        return match ($event->type) {
            GameEventType::Goal => "{$count}º gol do jogador na partida".($count >= 3 ? ' · HAT-TRICK' : ''),
            GameEventType::YellowCard => "{$count}º cartão amarelo",
            default => null,
        };
    }
}

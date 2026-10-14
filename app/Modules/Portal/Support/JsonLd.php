<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Team;

/**
 * Dados estruturados (schema.org, JSON-LD) das páginas públicas: jogo (`SportsEvent`), equipe (`SportsTeam`) e edição.
 * Só o que o portal já mostra a qualquer visitante: nomes das equipes, datas, local, placar do jogo decidido e links públicos.
 * Nunca atletas, cartões, escalação ou documentos.
 */
final class JsonLd
{
    /** @return array<string, mixed>|null null enquanto o jogo não tem as duas equipes definidas */
    public static function game(Game $game, bool $withContext = true): ?array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage']);
        $home = $game->homeEntry?->team;
        $away = $game->awayEntry?->team;

        if ($home === null || $away === null) {
            return null;
        }

        $result = $game->status->isDecided() ? " Resultado: {$home->name} {$game->home_score} x {$game->away_score} {$away->name}." : '';
        $data = [
            '@type' => 'SportsEvent',
            'name' => "{$home->name} x {$away->name}",
            'description' => "{$game->division->season->competition->name} · {$game->division->name} · {$game->stage->name}.{$result}",
            'url' => route('public.games.show', $game->getKey()),
            'eventStatus' => self::status($game->status),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'homeTeam' => self::team($home),
            'awayTeam' => self::team($away),
        ];

        if ($game->scheduled_at !== null) {
            $data['startDate'] = $game->scheduled_at->toIso8601String();
        }

        if ($game->venue !== null) {
            $data['location'] = ['@type' => 'Place', 'name' => $game->venue->name];
        }

        return $withContext ? ['@context' => 'https://schema.org'] + $data : $data;
    }

    /** @return array<string, mixed> */
    public static function teamPage(Team $team): array
    {
        return ['@context' => 'https://schema.org'] + self::team($team) + array_filter(['location' => $team->city ? ['@type' => 'Place', 'name' => $team->city] : null]);
    }

    /**
     * A edição como evento esportivo, com os primeiros jogos como subeventos.
     *
     * @param  iterable<int, Game>  $games
     * @return array<string, mixed>
     */
    public static function season(Competition $competition, Season $season, iterable $games): array
    {
        $sub = [];

        foreach ($games as $game) {
            $item = self::game($game, false);

            if ($item !== null) {
                $sub[] = $item;
            }
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => "{$competition->name} {$season->name}",
            'url' => route('portal.season', [$competition->slug, $season->getKey()]),
            'startDate' => $season->starts_on?->toDateString(),
            'endDate' => $season->ends_on?->toDateString(),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'subEvent' => $sub === [] ? null : $sub,
        ], fn ($value) => $value !== null);
    }

    /** @return array<string, mixed> */
    private static function team(Team $team): array
    {
        return array_filter([
            '@type' => 'SportsTeam',
            'name' => $team->name,
            'url' => route('portal.team', $team->getKey()),
            'logo' => $team->logoUrl(512),
        ], fn ($value) => $value !== null);
    }

    private static function status(GameStatus $status): string
    {
        return match ($status) {
            GameStatus::Postponed, GameStatus::Suspended => 'https://schema.org/EventPostponed',
            GameStatus::Cancelled => 'https://schema.org/EventCancelled',
            default => 'https://schema.org/EventScheduled',
        };
    }
}

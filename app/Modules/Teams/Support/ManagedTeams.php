<?php

namespace App\Modules\Teams\Support;

use App\Models\User;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamManager;
use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo do gestor de equipe (ADR 0003): as equipes a que o usuário está vinculado na organização atual e, por elas,
 * as inscrições (`Entry`) e os jogos. Tudo fora desse escopo responde 404 — a existência do registro não é revelada.
 */
final class ManagedTeams
{
    /** @return list<string> */
    public static function ids(User $user): array
    {
        return TeamManager::query()->where('user_id', $user->getKey())->pluck('team_id')->all();
    }

    /** @return Builder<Team> */
    public static function teams(User $user): Builder
    {
        return Team::query()->whereIn('id', self::ids($user));
    }

    /** @return Builder<Entry> */
    public static function entries(User $user): Builder
    {
        return Entry::query()->whereIn('team_id', self::ids($user));
    }

    /** @return Builder<Game> */
    public static function games(User $user): Builder
    {
        $entries = self::entries($user)->select('id');

        return Game::query()->where(fn (Builder $query) => $query->whereIn('home_entry_id', $entries)->orWhereIn('away_entry_id', $entries));
    }

    public static function authorizeTeam(User $user, Team $team): void
    {
        abort_unless(in_array($team->getKey(), self::ids($user), true), 404);
    }

    public static function authorizeEntry(User $user, Entry $entry): void
    {
        abort_unless(in_array($entry->team_id, self::ids($user), true), 404);
    }

    /**
     * Confere que o jogo é de uma equipe do gestor e devolve a inscrição DELE no jogo (a do mandante, se ele gerencia as duas).
     */
    public static function entryInGame(User $user, Game $game): Entry
    {
        $teams = self::ids($user);

        foreach ([$game->homeEntry, $game->awayEntry] as $entry) {
            if ($entry !== null && in_array($entry->team_id, $teams, true)) {
                return $entry;
            }
        }

        abort(404);
    }
}

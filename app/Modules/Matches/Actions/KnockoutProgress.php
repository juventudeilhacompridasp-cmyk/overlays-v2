<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Scheduling\TieResolver;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Avanço do mata-mata: descobre o vencedor do confronto (um ou dois jogos) e o coloca no confronto seguinte
 * (ou o retira, se o jogo foi reaberto). Jogos de pontos corridos/grupos não têm `tie_id` e passam direto.
 *
 * Lados do confronto: um jogo → a = mandante, b = visitante. Ida e volta → na ida o mandante é o lado b
 * (pior cabeça de chave) e na volta é o lado a.
 */
final class KnockoutProgress
{
    /** Chamar depois que o jogo foi encerrado, teve W.O. ou foi reaberto. */
    public function sync(Game $game): void
    {
        if ($game->tie_id === null || $game->next_tie_id === null || $game->next_slot === null) {
            return;
        }

        $games = $this->tieGames($game);
        $decided = $games->filter(fn (Game $leg) => $leg->status->isDecided());
        $winner = $decided->count() === $games->count() ? TieResolver::winner($this->legs($decided), $games->count()) : null;

        $this->place($game->next_tie_id, $game->next_slot, $winner);
    }

    /** Impede encerrar o último jogo de um confronto empatado sem pênaltis. */
    public function assertResolvable(Game $game): void
    {
        if ($game->tie_id === null) {
            return;
        }

        $games = $this->tieGames($game);
        $counted = $games->filter(fn (Game $leg) => $leg->is($game) || $leg->status->isDecided());

        if ($counted->count() < $games->count()) {
            return; // ainda falta um jogo do confronto (ex.: a volta)
        }

        if (TieResolver::winner($this->legs($counted), $games->count()) === null) {
            throw new InvalidArgumentException('Empate no mata-mata: informe a disputa de pênaltis antes de encerrar o jogo.');
        }
    }

    /** Impede reabrir um jogo cujo vencedor já está jogando o confronto seguinte. */
    public function assertCanRetract(Game $game): void
    {
        if ($game->next_tie_id === null) {
            return;
        }

        $started = Game::query()->where('tie_id', $game->next_tie_id)
            ->where(fn ($query) => $query->where('status', '!=', 'scheduled')->orWhereHas('events'))
            ->exists();

        if ($started) {
            throw new InvalidArgumentException('O confronto seguinte já começou. Reabra os jogos dele antes de alterar este resultado.');
        }
    }

    /** Coloca (ou limpa, com null) uma equipe em um lado de um confronto. */
    public function place(string $tieId, string $slot, ?string $entryId): void
    {
        $games = Game::query()->where('tie_id', $tieId)->orderBy('leg')->get();
        $twoLegs = $games->count() > 1;

        foreach ($games as $game) {
            $home = $twoLegs ? ($game->leg === 1 ? $slot === 'b' : $slot === 'a') : $slot === 'a';

            $game->forceFill([$home ? 'home_entry_id' : 'away_entry_id' => $entryId])->save();
        }
    }

    /** @return Collection<int, Game> */
    private function tieGames(Game $game): Collection
    {
        return Game::query()->where('tie_id', $game->tie_id)->orderBy('leg')->get();
    }

    /**
     * @param  Collection<int, Game>  $games
     * @return list<array{home: string, away: string, home_score: int, away_score: int, home_penalties: ?int, away_penalties: ?int}>
     */
    private function legs(Collection $games): array
    {
        return $games->map(fn (Game $leg) => [
            'home' => (string) $leg->home_entry_id,
            'away' => (string) $leg->away_entry_id,
            'home_score' => (int) $leg->home_score,
            'away_score' => (int) $leg->away_score,
            'home_penalties' => $leg->home_penalties === null ? null : (int) $leg->home_penalties,
            'away_penalties' => $leg->away_penalties === null ? null : (int) $leg->away_penalties,
        ])->values()->all();
    }
}

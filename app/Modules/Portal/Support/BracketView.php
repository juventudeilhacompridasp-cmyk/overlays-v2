<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Scheduling\KnockoutBracket;
use App\Modules\Matches\Scheduling\TieResolver;
use Illuminate\Support\Collection;

/**
 * Chaveamento visual do mata-mata para o portal: rodadas → confrontos (um jogo ou ida e volta), com placar agregado,
 * vencedor (mesmo critério de `KnockoutProgress`: soma de gols e, se empatar, pênaltis do último jogo) e o campeão.
 * Só times, placares e datas; nada de atletas.
 */
final class BracketView
{
    /**
     * @return array{rounds: list<array<string, mixed>>, champion: array<string, mixed>|null}|null
     */
    public static function for(Stage $stage): ?array
    {
        $games = Game::query()->where('stage_id', $stage->getKey())->whereNotNull('tie_id')
            ->with(['homeEntry.team', 'awayEntry.team', 'venue'])
            ->orderBy('round')->orderBy('number')->orderBy('leg')->get();

        if ($games->isEmpty()) {
            return null;
        }

        $total = (int) $games->max('round');
        $rounds = [];

        foreach ($games->groupBy('round')->sortKeys() as $round => $roundGames) {
            $rounds[] = [
                'label' => KnockoutBracket::roundLabel($total, (int) $round),
                'ties' => $roundGames->groupBy('tie_id')->map(fn (Collection $legs) => self::tie($legs->sortBy('leg')->values()))->values()->all(),
            ];
        }

        $final = $rounds[count($rounds) - 1]['ties'][0] ?? null;
        $champion = null;

        if ($final !== null) {
            $champion = $final['a']['winner'] ? $final['a']['team'] : ($final['b']['winner'] ? $final['b']['team'] : null);
        }

        return ['rounds' => $rounds, 'champion' => $champion];
    }

    /**
     * Lado a = mandante do último jogo do confronto (na ida e volta, o da volta); lado b = o outro.
     *
     * @param  Collection<int, Game>  $legs
     * @return array<string, mixed>
     */
    private static function tie(Collection $legs): array
    {
        /** @var Game $last */
        $last = $legs->last();
        $a = $last->home_entry_id;
        $b = $last->away_entry_id;

        $counted = $legs->filter(fn (Game $leg) => in_array($leg->status, [GameStatus::Finished, GameStatus::Walkover, GameStatus::InProgress], true));
        $score = function (?string $entryId) use ($counted): ?int {
            if ($entryId === null || $counted->isEmpty()) {
                return null;
            }

            return (int) $counted->sum(fn (Game $leg) => $leg->home_entry_id === $entryId ? $leg->home_score : ($leg->away_entry_id === $entryId ? $leg->away_score : 0));
        };

        $decided = $legs->every(fn (Game $leg) => $leg->status->isDecided());
        $winner = $decided && $a !== null && $b !== null
            ? TieResolver::winner($legs->map(fn (Game $leg) => [
                'home' => (string) $leg->home_entry_id,
                'away' => (string) $leg->away_entry_id,
                'home_score' => (int) $leg->home_score,
                'away_score' => (int) $leg->away_score,
                'home_penalties' => $leg->home_penalties === null ? null : (int) $leg->home_penalties,
                'away_penalties' => $leg->away_penalties === null ? null : (int) $leg->away_penalties,
            ])->all(), $legs->count())
            : null;

        $penalties = $decided && $last->home_penalties !== null && $last->away_penalties !== null
            ? ['a' => (int) $last->home_penalties, 'b' => (int) $last->away_penalties]
            : null;

        return [
            'id' => $last->tie_id,
            'a' => ['team' => GamePresenter::team($last->homeEntry), 'score' => $score($a), 'winner' => $winner !== null && $winner === $a],
            'b' => ['team' => GamePresenter::team($last->awayEntry), 'score' => $score($b), 'winner' => $winner !== null && $winner === $b],
            'decided' => $decided,
            'live' => $legs->contains(fn (Game $leg) => $leg->status === GameStatus::InProgress),
            'penalties' => $penalties,
            'legs' => $legs->map(fn (Game $leg) => [
                'url' => route('public.games.show', $leg->getKey()),
                'leg' => $leg->leg,
                'home' => $leg->homeEntry?->team->name,
                'away' => $leg->awayEntry?->team->name,
                'home_score' => $leg->home_score,
                'away_score' => $leg->away_score,
                'status_label' => $leg->status->label(),
                'decided' => $leg->status->isDecided(),
                'scheduled_at' => $leg->scheduled_at?->format('Y-m-d\TH:i'),
                'venue' => $leg->venue?->name,
            ])->values()->all(),
        ];
    }
}

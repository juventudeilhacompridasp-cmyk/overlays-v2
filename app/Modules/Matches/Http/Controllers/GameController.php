<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Actions\ReopenGame;
use App\Modules\Matches\Http\Requests\GameResultRequest;
use App\Modules\Matches\Http\Requests\GameScheduleRequest;
use App\Modules\Matches\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GameController extends Controller
{
    public function update(GameScheduleRequest $request, Game $game): RedirectResponse
    {
        if ($game->status->isDecided()) {
            return back()->with('error', 'O jogo já foi encerrado. Reabra para alterar data, local ou situação.');
        }

        $game->update($request->validated());

        return back()->with('success', 'Jogo atualizado.');
    }

    public function result(GameResultRequest $request, Game $game, RecordQuickResult $record): RedirectResponse
    {
        try {
            $record->handle($game, $request->integer('home_score'), $request->integer('away_score'), $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Resultado lançado.');
    }

    public function walkover(Request $request, Game $game, RegisterWalkover $walkover): RedirectResponse
    {
        $validated = $request->validate(['winner' => ['required', 'in:home,away']]);

        $winner = $validated['winner'] === 'home' ? $game->homeEntry : $game->awayEntry;

        if ($winner === null) {
            return back()->with('error', 'O jogo ainda não tem os dois confrontantes definidos.');
        }

        try {
            $walkover->handle($game, $winner);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'W.O. registrado.');
    }

    public function reopen(Game $game, ReopenGame $reopen): RedirectResponse
    {
        try {
            $reopen->handle($game);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Jogo reaberto. Lance o resultado novamente.');
    }
}

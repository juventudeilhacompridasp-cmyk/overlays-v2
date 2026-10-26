<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\CloseGameSheet;
use App\Modules\Matches\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Fechamento da súmula de um jogo encerrado (`operate-games`). */
class SheetClosingController extends Controller
{
    public function store(Request $request, Game $game, CloseGameSheet $close): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        try {
            $close->handle($game, $request->user(), $data['notes'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Súmula fechada.');
    }
}

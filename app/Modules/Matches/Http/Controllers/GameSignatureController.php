<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\GameSignatureActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GameSignatureController extends Controller
{
    public function show(string $token): View
    {
        return view('signatures.confirm', ['token' => $token]);
    }

    public function confirm(Request $request, string $token, GameSignatureActions $actions): RedirectResponse
    {
        $signature = $actions->confirm($token, $request);

        return redirect()->route('game-signatures.confirm', $token)->with($signature === null ? 'error' : 'success', $signature === null ? 'Este convite expirou ou já foi utilizado.' : 'Assinatura confirmada com sucesso.');
    }
}

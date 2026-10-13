<?php

namespace App\Modules\Market\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Market\Actions\AnswerContactRequest;
use App\Modules\Market\Actions\BlockTeam;
use App\Modules\Market\Actions\FileMarketReport;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** O atleta responde aos pedidos de contato e bloqueia equipes (ADR 0008, parte 3). Sempre no próprio perfil (404 fora). */
class AthleteRequestController extends Controller
{
    public function accept(Request $request, ContactRequest $contactRequest, AnswerContactRequest $answer): RedirectResponse
    {
        $this->own($request, $contactRequest);

        return $this->run(fn () => $answer->accept($contactRequest), 'Pedido aceito: a equipe agora vê o seu contato.');
    }

    public function decline(Request $request, ContactRequest $contactRequest, AnswerContactRequest $answer): RedirectResponse
    {
        $this->own($request, $contactRequest);

        return $this->run(fn () => $answer->decline($contactRequest), 'Pedido recusado.');
    }

    public function block(Request $request, ContactRequest $contactRequest, BlockTeam $blocks): RedirectResponse
    {
        $this->own($request, $contactRequest);

        $blocks->block($contactRequest->profile, $contactRequest->team);

        return back()->with('success', 'Equipe bloqueada: ela não vê mais o seu perfil.');
    }

    public function report(Request $request, ContactRequest $contactRequest, FileMarketReport $reports): RedirectResponse
    {
        $this->own($request, $contactRequest);

        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']], [], ['reason' => 'motivo']);

        try {
            $reports->byAthlete($request->user(), $contactRequest->profile, $contactRequest->team, trim($data['reason']));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Denúncia enviada à organização.');
    }

    public function unblock(Request $request, MarketBlock $block, BlockTeam $blocks): RedirectResponse
    {
        abort_unless($block->profile->user_id === $request->user()->getKey(), 404);

        $blocks->unblock($block);

        return back()->with('success', 'Equipe desbloqueada.');
    }

    private function own(Request $request, ContactRequest $contactRequest): void
    {
        abort_unless($contactRequest->profile->user_id === $request->user()->getKey(), 404);
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $success);
    }
}

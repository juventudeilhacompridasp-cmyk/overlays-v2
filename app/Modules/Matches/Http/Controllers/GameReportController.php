<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\GameSignatureActions;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\GameReportPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** PDFs internos da partida: escalação antes do jogo e relatório final. */
final class GameReportController extends Controller
{
    public function lineup(Request $request, Game $game): Response
    {
        return $this->download($game, 'escalacao', GameReportPdf::lineup($game), $request->boolean('visualizar'));
    }

    public function sheet(Request $request, Game $game): Response
    {
        return $this->download($game, 'sumula-oficial', GameReportPdf::officialSheet($game), $request->boolean('visualizar'));
    }

    public function finalReport(Request $request, Game $game): Response
    {
        $type = (string) $request->query('tipo', 'full');
        $label = match ($type) {
            'lineup' => 'escalacao',
            'activities' => 'atividades',
            default => 'relatorio-final',
        };

        return $this->download($game, $label, GameReportPdf::finalReport($game, $type), $request->boolean('visualizar'));
    }

    public function inviteSignature(Request $request, Game $game, GameSignatureActions $actions): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'in:sheet,lineup,final'],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
        ]);
        $document = match ($data['document_type']) {
            'lineup' => GameReportPdf::lineup($game),
            'final' => GameReportPdf::finalReport($game),
            default => GameReportPdf::officialSheet($game),
        };

        $actions->invite($game, $data['document_type'], $document, $data['name'], $data['role'], $data['email']);

        return back()->with('success', 'Convite de assinatura enviado.');
    }

    private function download(Game $game, string $kind, string $pdf, bool $inline = false): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$kind.'-'.$game->getKey().'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

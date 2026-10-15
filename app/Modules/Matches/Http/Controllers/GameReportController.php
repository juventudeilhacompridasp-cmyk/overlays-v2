<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\GameReportPdf;
use App\Modules\Matches\Support\PdfReport;
use Symfony\Component\HttpFoundation\Response;

/** PDFs internos da partida: escalação antes do jogo e relatório final. */
final class GameReportController extends Controller
{
    public function lineup(Game $game): Response
    {
        return $this->download($game, 'escalacao', GameReportPdf::lineup($game));
    }

    public function finalReport(Game $game): Response
    {
        return $this->download($game, 'relatorio-final', GameReportPdf::finalReport($game));
    }

    /** @param list<string> $lines */
    private function download(Game $game, string $kind, array $lines): Response
    {
        return response(PdfReport::make($lines), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$kind.'-'.$game->getKey().'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

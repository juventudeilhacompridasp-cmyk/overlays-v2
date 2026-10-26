<?php

namespace App\Modules\Competitions\Support;

use App\Modules\Broadcast\Models\OverlaySponsor;
use App\Modules\Competitions\Models\Season;
use App\Support\Pdf\PdfDocument;

/** Relatório institucional leve para patrocinadores e prestação de contas da edição. */
final class SponsorReportPdf
{
    public static function for(Season $season): string
    {
        $season->load('competition');
        $divisions = $season->divisions()->withCount(['games', 'entries'])->orderBy('name')->get();
        $sponsors = OverlaySponsor::query()->where('active', true)->orderBy('sort')->orderBy('name')->get();
        $pdf = new PdfDocument('Relatório institucional', 'Relatório institucional · '.$season->competition->name.' · '.$season->name);
        $pdf->text(PdfDocument::MARGIN, $pdf->y(), 'Relatório institucional', 22, true, '#064e3b');
        $pdf->moveTo($pdf->y() + 34);
        $pdf->text(PdfDocument::MARGIN, $pdf->y(), $season->competition->name.' · '.$season->name, 12, false, '#374151');
        $pdf->skip(28);
        self::section($pdf, 'Resumo da edição');
        self::metric($pdf, 'Divisões', (string) $divisions->count(), 36);
        self::metric($pdf, 'Jogos previstos', (string) $divisions->sum('games_count'), 140);
        self::metric($pdf, 'Equipes inscritas', (string) $divisions->sum('entries_count'), 244);
        $pdf->moveTo($pdf->y() + 24);
        self::section($pdf, 'Divisões e alcance');
        foreach ($divisions as $division) {
            $pdf->ensure(24);
            $pdf->text(PdfDocument::MARGIN, $pdf->y(), $division->name, 10, true, '#111827', 'left', 250);
            $pdf->text(300, $pdf->y(), $division->entries_count.' equipes · '.$division->games_count.' jogos', 10, false, '#4b5563');
            $pdf->line(PdfDocument::MARGIN, $pdf->y() + 17, PdfDocument::WIDTH - PdfDocument::MARGIN, $pdf->y() + 17, '#e5e7eb');
            $pdf->moveTo($pdf->y() + 24);
        }
        self::section($pdf, 'Patrocinadores ativos');
        if ($sponsors->isEmpty()) {
            $pdf->text(PdfDocument::MARGIN, $pdf->y(), 'Nenhum patrocinador ativo cadastrado.', 10, false, '#6b7280');
            $pdf->skip(20);
        } else {
            foreach ($sponsors as $sponsor) {
                $pdf->ensure(24);
                $pdf->text(PdfDocument::MARGIN, $pdf->y(), $sponsor->name, 10, true, '#111827');
                $pdf->text(300, $pdf->y(), 'Presença nos overlays de transmissão', 9, false, '#4b5563');
                $pdf->moveTo($pdf->y() + 22);
            }
        }

        return $pdf->output();
    }

    private static function section(PdfDocument $pdf, string $title): void
    {
        $pdf->ensure(32);
        $pdf->text(PdfDocument::MARGIN, $pdf->y(), $title, 12, true, '#047857');
        $pdf->moveTo($pdf->y() + 20);
    }

    private static function metric(PdfDocument $pdf, string $label, string $value, float $x): void
    {
        $pdf->rect($x, $pdf->y(), 92, 42, '#f0fdf4', '#bbf7d0', 8);
        $pdf->text($x + 46, $pdf->y() + 8, $value, 18, true, '#065f46', 'center', 84);
        $pdf->text($x + 46, $pdf->y() + 29, $label, 8, false, '#047857', 'center', 84);
    }
}

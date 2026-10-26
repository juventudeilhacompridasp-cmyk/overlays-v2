<?php

use App\Support\Pdf\PdfDocument;
use App\Support\Pdf\PdfMetrics;

it('gera um PDF válido com título e rodapé', function () {
    $pdf = new PdfDocument('Teste', 'Rodapé de teste');
    $pdf->text(36, 40, 'Olá', 12, true);

    $output = $pdf->output();

    expect($output)->toStartWith('%PDF-1.4')
        ->and($output)->toContain('/Count 1')
        ->and($output)->toContain("Rodap\xE9 de teste")
        ->and($output)->toEndWith("%%EOF\n");
});

it('escreve acentos em WinAnsi e escapa parênteses e barras', function () {
    $pdf = new PdfDocument('Título', '');
    $pdf->text(36, 40, 'Ação (1) \\ ok');

    $output = $pdf->output();

    expect($output)->toContain("A\xE7\xE3o \\(1\\) \\\\ ok")
        ->and($output)->toContain('/Encoding /WinAnsiEncoding');
});

it('numera as páginas no rodapé e cria páginas a pedido', function () {
    $pdf = new PdfDocument('Teste', 'Rodapé');
    $pdf->newPage();

    $output = $pdf->output();

    expect($output)->toContain('/Count 2')
        ->and($output)->toContain("P\xE1gina 2 de 2");
});

it('começa outra página quando o conteúdo não cabe', function () {
    $pdf = new PdfDocument('Teste');

    expect($pdf->ensure(100))->toBeFalse();

    $pdf->moveTo(780);

    expect($pdf->ensure(100))->toBeTrue()
        ->and($pdf->y())->toBe(PdfDocument::MARGIN);
});

it('quebra o texto em linhas que cabem na largura e corta o que não cabe', function () {
    $pdf = new PdfDocument('Teste');
    $lines = $pdf->wrap('uma frase razoavelmente longa para quebrar em várias linhas pequenas', 120, 10);

    expect(count($lines))->toBeGreaterThan(2);
    foreach ($lines as $line) {
        expect(PdfMetrics::width($line, 10))->toBeLessThanOrEqual(120.5);
    }

    expect($pdf->fit('Associação Atlética Ilha Comprida', 80, 10, true))->toEndWith('...')
        ->and(PdfMetrics::width($pdf->fit('Associação Atlética Ilha Comprida', 80, 10, true), 10, true))->toBeLessThanOrEqual(80);
});

it('mede o texto em negrito mais largo que o normal e trata acentos como a letra base', function () {
    expect(PdfMetrics::width('Equipe', 10, true))->toBeGreaterThan(PdfMetrics::width('Equipe', 10))
        ->and(PdfMetrics::width('ação', 10))->toBe(PdfMetrics::width('acao', 10));
});

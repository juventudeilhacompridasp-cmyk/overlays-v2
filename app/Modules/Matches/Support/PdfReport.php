<?php

namespace App\Modules\Matches\Support;

/** Escritor PDF mínimo, sem dependência externa, para relatórios do painel. */
final class PdfReport
{
    /** @param list<string> $lines */
    public static function make(array $lines): string
    {
        // O escritor é propositalmente pequeno, mas não pode deixar uma escalação longa sair da página: uma linha com
        // muitos atletas era cortada pelo leitor do PDF. Quebramos antes de paginar, mantendo linhas vazias como separadores.
        $wrapped = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $line) ?? '');
            $parts = $line === '' ? [''] : explode("\n", wordwrap($line, 88, "\n", false));
            $wrapped = [...$wrapped, ...$parts];
        }

        $pages = array_chunk($wrapped, 44);
        $pages = $pages === [] ? [['']] : $pages;
        $pageIds = [];
        for ($id = 4; $id <= 3 + count($pages) * 2; $id += 2) {
            $pageIds[] = "{$id} 0 R";
        }
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.count($pages).' >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        foreach ($pages as $page) {
            $content = "BT\n/F1 10 Tf\n40 800 Td\n";
            foreach ($page as $line) {
                $content .= '('.self::escape($line).") Tj\n0 -16 Td\n";
            }
            $content .= "ET\n";
            $contentObject = count($objects) + 2;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[] = '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n %s', $offset, "\n");
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    private static function escape(string $line): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii === false ? $line : $ascii);
    }
}

<?php

namespace App\Support\Pdf;

/**
 * Escritor PDF nativo (sem dependência externa) com o necessário para relatórios bonitos: páginas A4, retângulos e círculos
 * coloridos, texto em Helvetica normal/negrito com acentos (WinAnsi), quebra de linha, alinhamento e rodapé com a
 * numeração das páginas. As coordenadas são "de cima para baixo", em pontos (A4 = 595 x 842).
 */
final class PdfDocument
{
    public const WIDTH = 595.28;

    public const HEIGHT = 841.89;

    public const MARGIN = 36.0;

    private const BOTTOM = 52.0;

    /** @var list<string> */
    private array $pages = [];

    private string $buffer = '';

    private float $y = self::MARGIN;

    public function __construct(private readonly string $title, private readonly string $footer = '')
    {
        $this->y = self::MARGIN;
    }

    /** Largura útil entre as margens. */
    public function contentWidth(): float
    {
        return self::WIDTH - 2 * self::MARGIN;
    }

    public function y(): float
    {
        return $this->y;
    }

    public function moveTo(float $y): void
    {
        $this->y = $y;
    }

    public function skip(float $height): void
    {
        $this->y += $height;
    }

    /** Começa outra página se não couber `$height` abaixo do cursor; devolve true quando começou. */
    public function ensure(float $height): bool
    {
        if ($this->y + $height <= self::HEIGHT - self::BOTTOM || $this->y <= self::MARGIN) {
            return false;
        }

        $this->newPage();

        return true;
    }

    public function newPage(): void
    {
        if ($this->buffer !== '' || $this->pages === []) {
            $this->pages[] = $this->buffer;
        }

        $this->buffer = '';
        $this->y = self::MARGIN;
    }

    /** Espaço que ainda cabe na página atual. */
    public function remaining(): float
    {
        return self::HEIGHT - self::BOTTOM - $this->y;
    }

    public function rect(float $x, float $y, float $w, float $h, ?string $fill = null, ?string $stroke = null, float $radius = 0, float $lineWidth = 0.7): void
    {
        if ($fill === null && $stroke === null) {
            return;
        }

        $this->buffer .= 'q '.($fill !== null ? self::color($fill, false).' ' : '').($stroke !== null ? self::color($stroke, true).' '.self::n($lineWidth).' w ' : '');
        $this->buffer .= $this->path($x, $y, $w, $h, min($radius, $w / 2, $h / 2));
        $this->buffer .= ($fill !== null && $stroke !== null ? 'B' : ($fill !== null ? 'f' : 'S'))." Q\n";
    }

    public function circle(float $cx, float $cy, float $r, string $fill): void
    {
        $this->rect($cx - $r, $cy - $r, 2 * $r, 2 * $r, $fill, null, $r);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#d1d5db', float $width = 0.7, bool $dashed = false): void
    {
        $this->buffer .= 'q '.self::color($color, true).' '.self::n($width).' w '.($dashed ? '[2 2] 0 d ' : '').self::n($x1).' '.self::n(self::HEIGHT - $y1).' m '.self::n($x2).' '.self::n(self::HEIGHT - $y2)." l S Q\n";
    }

    /**
     * Escreve uma linha de texto; `$y` é o topo da linha. `$align` = left|center|right em relação a `$x` (e `$width`, se informada).
     */
    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, string $color = '#111827', string $align = 'left', ?float $width = null): void
    {
        if ($text === '') {
            return;
        }

        if ($width !== null) {
            $text = $this->fit($text, $width, $size, $bold);
        }

        $textWidth = PdfMetrics::width($text, $size, $bold);
        $left = match ($align) {
            'right' => $x - $textWidth,
            'center' => $x - $textWidth / 2,
            default => $x,
        };

        $this->buffer .= 'BT '.self::color($color, false).' /'.($bold ? 'F2' : 'F1').' '.self::n($size).' Tf '.self::n($left).' '.self::n(self::HEIGHT - $y - $size * 0.82).' Td ('.self::escape($text).") Tj ET\n";
    }

    /** Corta o texto com reticências para caber na largura. */
    public function fit(string $text, float $width, float $size, bool $bold = false): string
    {
        if (PdfMetrics::width($text, $size, $bold) <= $width) {
            return $text;
        }

        $chars = mb_str_split($text);

        while ($chars !== [] && PdfMetrics::width(implode('', $chars).'...', $size, $bold) > $width) {
            array_pop($chars);
        }

        return rtrim(implode('', $chars)).'...';
    }

    /**
     * Quebra o texto em linhas que cabem na largura (palavras muito longas são cortadas).
     *
     * @return list<string>
     */
    public function wrap(string $text, float $width, float $size = 10, bool $bold = false): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', trim($text)) ?: [''] as $paragraph) {
            $current = '';

            foreach (preg_split('/\s+/u', trim($paragraph)) ?: [] as $word) {
                if ($word === '') {
                    continue;
                }

                $candidate = $current === '' ? $word : $current.' '.$word;

                if (PdfMetrics::width($candidate, $size, $bold) <= $width) {
                    $current = $candidate;

                    continue;
                }

                if ($current !== '') {
                    $lines[] = $current;
                }

                $current = $word;

                while (PdfMetrics::width($current, $size, $bold) > $width && mb_strlen($current) > 1) {
                    $cut = mb_strlen($current) - 1;
                    while ($cut > 1 && PdfMetrics::width(mb_substr($current, 0, $cut), $size, $bold) > $width) {
                        $cut--;
                    }
                    $lines[] = mb_substr($current, 0, $cut);
                    $current = mb_substr($current, $cut);
                }
            }

            $lines[] = $current;
        }

        return $lines;
    }

    /** Gera o arquivo, com o rodapé "Página x de y" em cada página. */
    public function output(): string
    {
        $pages = [...$this->pages, $this->buffer];
        $total = count($pages);
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Title ('.self::escape($this->title).') /Producer (Plataforma de competições) /CreationDate (D:'.gmdate('YmdHis').'Z) >>',
        ];
        $kids = [];

        foreach ($pages as $index => $content) {
            $this->buffer = '';
            $this->drawFooter($index + 1, $total);
            $content .= $this->buffer;

            $pageId = count($objects) + 1;
            $kids[] = "{$pageId} 0 R";
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.($pageId + 1).' 0 R >>';
            $objects[] = '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
        }

        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$total.' >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R /Info 5 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function drawFooter(int $page, int $total): void
    {
        $y = self::HEIGHT - 34;
        $this->line(self::MARGIN, $y - 6, self::WIDTH - self::MARGIN, $y - 6, '#e5e7eb');
        $this->text(self::MARGIN, $y, $this->footer, 8, false, '#6b7280', 'left', $this->contentWidth() - 90);
        $this->text(self::WIDTH - self::MARGIN, $y, "Página {$page} de {$total}", 8, false, '#6b7280', 'right');
    }

    private function path(float $x, float $y, float $w, float $h, float $r): string
    {
        $top = self::HEIGHT - $y;
        $bottom = $top - $h;

        if ($r <= 0) {
            return self::n($x).' '.self::n($bottom).' '.self::n($w).' '.self::n($h).' re ';
        }

        $k = $r * 0.5523;
        $right = $x + $w;

        return self::n($x + $r).' '.self::n($top).' m '
            .self::n($right - $r).' '.self::n($top).' l '
            .self::n($right - $r + $k).' '.self::n($top).' '.self::n($right).' '.self::n($top - $r + $k).' '.self::n($right).' '.self::n($top - $r).' c '
            .self::n($right).' '.self::n($bottom + $r).' l '
            .self::n($right).' '.self::n($bottom + $r - $k).' '.self::n($right - $r + $k).' '.self::n($bottom).' '.self::n($right - $r).' '.self::n($bottom).' c '
            .self::n($x + $r).' '.self::n($bottom).' l '
            .self::n($x + $r - $k).' '.self::n($bottom).' '.self::n($x).' '.self::n($bottom + $r - $k).' '.self::n($x).' '.self::n($bottom + $r).' c '
            .self::n($x).' '.self::n($top - $r).' l '
            .self::n($x).' '.self::n($top - $r + $k).' '.self::n($x + $r - $k).' '.self::n($top).' '.self::n($x + $r).' '.self::n($top).' c h ';
    }

    private static function color(string $hex, bool $stroke): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '000000';
        }

        $parts = array_map(fn (string $pair): string => self::n(hexdec($pair) / 255), str_split($hex, 2));

        return implode(' ', $parts).($stroke ? ' RG' : ' rg');
    }

    private static function n(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private static function escape(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $text) ?? '';
        $latin = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin);
    }
}

<?php

namespace App\Modules\Reports\Pdf;

/**
 * Escritor de PDF vetorial sem dependência externa: páginas A4, Helvetica/Helvetica-Bold com WinAnsiEncoding (acentos do
 * português), retângulos com cantos arredondados, linhas, círculos, fatias de rosca, ícones (caminhos de `ReportIcons`),
 * imagens JPEG, transparência e conteúdo comprimido (Flate). As coordenadas da API são em pontos com a ORIGEM NO TOPO
 * (y cresce para baixo, como na tela); a conversão para o sistema do PDF acontece aqui dentro.
 */
final class PdfDocument
{
    public const WIDTH = 595.28;

    public const HEIGHT = 841.89;

    /** Larguras (em milésimos do corpo) dos caracteres 32 a 126 da Helvetica e da Helvetica-Bold (métricas AFM padrão). */
    private const REGULAR = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    private const BOLD = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    /** Letras acentuadas medem como a letra base; símbolos comuns têm largura própria. */
    private const BASE = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'É' => 'E', 'Ê' => 'E', 'È' => 'E', 'Í' => 'I', 'Î' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ü' => 'U', 'Ç' => 'C', 'Ñ' => 'N',
    ];

    private const SYMBOLS = ['—' => 1000, '–' => 556, '…' => 1000, '·' => 278, '×' => 584, '°' => 400, 'º' => 365, 'ª' => 370, '•' => 350, '“' => 333, '”' => 333, '‘' => 222, '’' => 222];

    /** @var list<string> conteúdo de cada página */
    private array $pages = [];

    private int $page = -1;

    /** @var array<string, array{data: string, width: int, height: int}> */
    private array $images = [];

    /** @var array<string, string> opacidade => nome do estado gráfico */
    private array $alphas = [];

    public function __construct(private readonly string $title = 'Relatório') {}

    public function addPage(): int
    {
        $this->pages[] = '';
        $this->page = count($this->pages) - 1;

        return $this->page;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    /** Volta a desenhar numa página já criada (ex.: rodapé "página x de y" no fim). */
    public function onPage(int $index): void
    {
        $this->page = max(0, min($index, count($this->pages) - 1));
    }

    public function rect(float $x, float $y, float $w, float $h, ?string $fill, ?string $stroke = null, float $radius = 0, float $lineWidth = 1): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }

        $radius = min($radius, $w / 2, $h / 2);

        if ($radius <= 0) {
            $path = sprintf('%s %s %s %s re', self::n($x), self::n(self::HEIGHT - $y - $h), self::n($w), self::n($h));
        } else {
            $k = $radius * 0.5523;
            $points = [
                ['m', $x + $radius, $y],
                ['l', $x + $w - $radius, $y], ['c', $x + $w - $radius + $k, $y, $x + $w, $y + $radius - $k, $x + $w, $y + $radius],
                ['l', $x + $w, $y + $h - $radius], ['c', $x + $w, $y + $h - $radius + $k, $x + $w - $radius + $k, $y + $h, $x + $w - $radius, $y + $h],
                ['l', $x + $radius, $y + $h], ['c', $x + $radius - $k, $y + $h, $x, $y + $h - $radius + $k, $x, $y + $h - $radius],
                ['l', $x, $y + $radius], ['c', $x, $y + $radius - $k, $x + $radius - $k, $y, $x + $radius, $y],
            ];
            $path = $this->segments($points).' h';
        }

        $this->paint($path, $fill, $stroke, $lineWidth);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width = 1): void
    {
        $this->emit(sprintf('%s w %s %s %s m %s %s l S', self::n($width), $this->color($color, true), self::n($x1), self::n(self::HEIGHT - $y1), self::n($x2), self::n(self::HEIGHT - $y2)));
    }

    public function circle(float $cx, float $cy, float $r, ?string $fill, ?string $stroke = null, float $lineWidth = 1): void
    {
        $k = $r * 0.5523;
        $path = $this->segments([
            ['m', $cx + $r, $cy],
            ['c', $cx + $r, $cy + $k, $cx + $k, $cy + $r, $cx, $cy + $r],
            ['c', $cx - $k, $cy + $r, $cx - $r, $cy + $k, $cx - $r, $cy],
            ['c', $cx - $r, $cy - $k, $cx - $k, $cy - $r, $cx, $cy - $r],
            ['c', $cx + $k, $cy - $r, $cx + $r, $cy - $k, $cx + $r, $cy],
        ]).' h';

        $this->paint($path, $fill, $stroke, $lineWidth);
    }

    /** Fatia do círculo de `$from` a `$to` (frações de 0 a 1, no sentido horário a partir do topo). */
    public function wedge(float $cx, float $cy, float $r, float $from, float $to, string $fill): void
    {
        if ($to - $from <= 0) {
            return;
        }

        if ($to - $from >= 0.9999) {
            $this->circle($cx, $cy, $r, $fill);

            return;
        }

        $point = fn (float $t) => [$cx + $r * sin($t * 2 * M_PI), $cy - $r * cos($t * 2 * M_PI)];
        $segments = [['m', $cx, $cy], ['l', ...$point($from)]];
        $steps = (int) ceil(($to - $from) / 0.25);
        $step = ($to - $from) / $steps;

        for ($i = 0; $i < $steps; $i++) {
            $a = ($from + $i * $step) * 2 * M_PI;
            $b = ($from + ($i + 1) * $step) * 2 * M_PI;
            $k = 4 / 3 * tan(($b - $a) / 4) * $r;
            [$x0, $y0] = [$cx + $r * sin($a), $cy - $r * cos($a)];
            [$x3, $y3] = [$cx + $r * sin($b), $cy - $r * cos($b)];
            $segments[] = ['c', $x0 + $k * cos($a), $y0 + $k * sin($a), $x3 - $k * cos($b), $y3 - $k * sin($b), $x3, $y3];
        }

        $this->paint($this->segments($segments).' h', $fill, null, 1);
    }

    /** Polígono preenchido (pontos em coordenadas da página). @param  list<array{0: float, 1: float}>  $points */
    public function polygon(array $points, string $fill): void
    {
        $segments = [];

        foreach ($points as $index => [$x, $y]) {
            $segments[] = [$index === 0 ? 'm' : 'l', $x, $y];
        }

        $this->paint($this->segments($segments).' h', $fill, null, 1);
    }

    /** Ícone de `ReportIcons` (grade 24 x 24) desenhado num quadrado de `$size` pontos com o canto em (x, y). */
    public function icon(string $path, float $x, float $y, float $size, string $color): void
    {
        // Agrupa os números de cada comando ("C" leva 6, "M"/"L" levam 2, "Z" fecha) e converte para a página.
        preg_match_all('/[MLCZ][^MLCZ]*/', $path, $matches);
        $scale = $size / 24;
        $out = [];

        foreach ($matches[0] as $chunk) {
            $command = $chunk[0];

            if ($command === 'Z') {
                $out[] = 'h';

                continue;
            }

            preg_match_all('/-?\d*\.?\d+/', $chunk, $found);
            $numbers = array_map('floatval', $found[0]);
            $count = $command === 'C' ? 6 : 2;

            for ($i = 0; $i + $count <= count($numbers); $i += $count) {
                $coords = [];

                for ($j = 0; $j < $count; $j += 2) {
                    $coords[] = self::n($x + $numbers[$i + $j] * $scale);
                    $coords[] = self::n(self::HEIGHT - ($y + $numbers[$i + $j + 1] * $scale));
                }

                $out[] = implode(' ', $coords).' '.($command === 'C' ? 'c' : ($command === 'M' && $i === 0 ? 'm' : 'l'));
            }
        }

        $this->paint(implode(' ', $out), $color, null, 1);
    }

    /** Texto com a linha de base em `$y`. `$align`: left, center ou right (a partir de `$x`). */
    public function text(float $x, float $y, string $text, float $size, string $color = '#0f172a', bool $bold = false, string $align = 'left'): void
    {
        $text = self::clean($text);

        if ($text === '') {
            return;
        }

        $width = self::width($text, $size, $bold);
        $left = match ($align) {
            'center' => $x - $width / 2,
            'right' => $x - $width,
            default => $x,
        };

        $this->emit(sprintf('BT /%s %s Tf %s %s %s Td (%s) Tj ET', $bold ? 'F2' : 'F1', self::n($size), $this->color($color), self::n($left), self::n(self::HEIGHT - $y), self::escape(self::encode($text))));
    }

    /** Largura do texto em pontos (métricas AFM; acentuadas medem como a letra base). */
    public static function width(string $text, float $size, bool $bold = false): float
    {
        $table = $bold ? self::BOLD : self::REGULAR;
        $total = 0;

        foreach (mb_str_split(self::clean($text)) as $char) {
            $base = self::BASE[$char] ?? $char;
            $code = strlen($base) === 1 ? ord($base) : 0;
            $total += $code >= 32 && $code <= 126 ? $table[$code - 32] : (self::SYMBOLS[$char] ?? 556);
        }

        return $total * $size / 1000;
    }

    /** Corta com reticências para caber em `$max` pontos. */
    public static function fit(string $text, float $max, float $size, bool $bold = false): string
    {
        $text = self::clean($text);

        if (self::width($text, $size, $bold) <= $max) {
            return $text;
        }

        $chars = mb_str_split($text);

        while ($chars !== [] && self::width(implode('', $chars).'…', $size, $bold) > $max) {
            array_pop($chars);
        }

        return $chars === [] ? '' : rtrim(implode('', $chars)).'…';
    }

    /** @return list<string> linhas que cabem em `$max` pontos (quebra por palavra). */
    public static function wrap(string $text, float $max, float $size, bool $bold = false): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', self::clean($text)) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && self::width($candidate, $size, $bold) > $max) {
                $lines[] = $current;
                $current = self::fit($word, $max, $size, $bold);
            } else {
                $current = self::width($candidate, $size, $bold) > $max ? self::fit($candidate, $max, $size, $bold) : $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /** Registra uma imagem JPEG (bytes) com um nome para desenhar depois. */
    public function addJpeg(string $name, string $bytes, int $width, int $height): void
    {
        $this->images[$name] = ['data' => $bytes, 'width' => $width, 'height' => $height];
    }

    public function hasImage(string $name): bool
    {
        return isset($this->images[$name]);
    }

    public function image(string $name, float $x, float $y, float $w, float $h): void
    {
        if (! isset($this->images[$name])) {
            return;
        }

        $this->emit(sprintf('q %s 0 0 %s %s %s cm /%s Do Q', self::n($w), self::n($h), self::n($x), self::n(self::HEIGHT - $y - $h), $this->imageName($name)));
    }

    /** Desenha o que vier em `$draw` recortado ao retângulo (nada vaza para fora). */
    public function clipped(float $x, float $y, float $w, float $h, callable $draw): void
    {
        $this->emit(sprintf('q %s %s %s %s re W n', self::n($x), self::n(self::HEIGHT - $y - $h), self::n($w), self::n($h)));
        $draw($this);
        $this->emit('Q');
    }

    /** Desenha o que vier em `$draw` com transparência (0 a 1). */
    public function withOpacity(float $alpha, callable $draw): void
    {
        $alpha = max(0, min(1, $alpha));
        $key = number_format($alpha, 2, '.', '');
        $this->alphas[$key] ??= 'GS'.(count($this->alphas) + 1);
        $this->emit('q /'.$this->alphas[$key].' gs');
        $draw($this);
        $this->emit('Q');
    }

    public function output(): string
    {
        $pages = $this->pages === [] ? [''] : $this->pages;
        $objects = [];
        $reserve = function () use (&$objects): int {
            $objects[] = '';

            return count($objects);
        };

        $catalog = $reserve();
        $pagesId = $reserve();
        $info = $reserve();
        $regular = $reserve();
        $bold = $reserve();
        $objects[$regular - 1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$bold - 1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $xobjects = [];
        foreach ($this->images as $name => $image) {
            $id = $reserve();
            $objects[$id - 1] = sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream", $image['width'], $image['height'], strlen($image['data']), $image['data']);
            $xobjects[] = '/'.$this->imageName($name).' '.$id.' 0 R';
        }

        $states = [];
        foreach ($this->alphas as $alpha => $name) {
            $id = $reserve();
            $objects[$id - 1] = "<< /Type /ExtGState /ca {$alpha} /CA {$alpha} >>";
            $states[] = "/{$name} {$id} 0 R";
        }

        $resources = $reserve();
        $objects[$resources - 1] = '<< /Font << /F1 '.$regular.' 0 R /F2 '.$bold.' 0 R >>'
            .($xobjects !== [] ? ' /XObject << '.implode(' ', $xobjects).' >>' : '')
            .($states !== [] ? ' /ExtGState << '.implode(' ', $states).' >>' : '').' >>';

        $kids = [];
        foreach ($pages as $content) {
            $pageId = $reserve();
            $contentId = $reserve();
            $stream = function_exists('gzcompress') ? (string) gzcompress($content, 6) : $content;
            $filter = function_exists('gzcompress') ? ' /Filter /FlateDecode' : '';
            $objects[$pageId - 1] = sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources %d 0 R /Contents %d 0 R >>', $pagesId, self::n(self::WIDTH), self::n(self::HEIGHT), $resources, $contentId);
            $objects[$contentId - 1] = '<< /Length '.strlen($stream).$filter.">>\nstream\n".$stream."\nendstream";
            $kids[] = $pageId.' 0 R';
        }

        $objects[$catalog - 1] = '<< /Type /Catalog /Pages '.$pagesId.' 0 R >>';
        $objects[$pagesId - 1] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $objects[$info - 1] = '<< /Title ('.self::escape(self::encode(self::clean($this->title))).') /Producer (Relatorios da plataforma) /CreationDate (D:'.gmdate('YmdHis').'Z) >>';

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

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root {$catalog} 0 R /Info {$info} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /** Texto em uma linha, sem caracteres de controle nem o que a WinAnsi não representa (ex.: emojis). */
    public static function clean(string $text): string
    {
        $text = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
        $out = '';

        foreach (mb_str_split($text) as $char) {
            if (strlen($char) === 1 || self::encodable($char)) {
                $out .= $char;
            }
        }

        return trim($out);
    }

    private static function encodable(string $char): bool
    {
        $encoded = mb_convert_encoding($char, 'Windows-1252', 'UTF-8');

        return $encoded !== '?' && strlen($encoded) === 1;
    }

    private static function encode(string $text): string
    {
        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function imageName(string $name): string
    {
        return 'Im'.substr(md5($name), 0, 8);
    }

    /** @param  list<array<int, string|float>>  $segments  [operador, x, y, ...] em coordenadas da página */
    private function segments(array $segments): string
    {
        $out = [];

        foreach ($segments as $segment) {
            $operator = array_shift($segment);
            $coords = [];

            foreach (array_chunk($segment, 2) as [$px, $py]) {
                $coords[] = self::n((float) $px).' '.self::n(self::HEIGHT - (float) $py);
            }

            $out[] = implode(' ', $coords).' '.$operator;
        }

        return implode(' ', $out);
    }

    private function paint(string $path, ?string $fill, ?string $stroke, float $lineWidth): void
    {
        if ($fill === null && $stroke === null) {
            return;
        }

        $ops = [];

        if ($fill !== null) {
            $ops[] = $this->color($fill);
        }

        if ($stroke !== null) {
            $ops[] = $this->color($stroke, true);
            $ops[] = self::n($lineWidth).' w';
        }

        $this->emit(implode(' ', $ops).' '.$path.' '.($fill === null ? 'S' : ($stroke === null ? 'f' : 'B')));
    }

    private function color(string $hex, bool $stroke = false): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $rgb = preg_match('/^[0-9a-fA-F]{6}$/', $hex) === 1 ? array_map(fn (string $part) => hexdec($part) / 255, str_split($hex, 2)) : [0, 0, 0];

        return sprintf('%s %s %s %s', self::n($rgb[0]), self::n($rgb[1]), self::n($rgb[2]), $stroke ? 'RG' : 'rg');
    }

    private function emit(string $operators): void
    {
        if ($this->page < 0) {
            $this->addPage();
        }

        $this->pages[$this->page] .= $operators."\n";
    }

    private static function n(float $value): string
    {
        $text = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        return $text === '-0' || $text === '' ? '0' : $text;
    }
}

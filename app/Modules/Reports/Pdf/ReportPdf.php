<?php

namespace App\Modules\Reports\Pdf;

/**
 * Diagrama o documento de blocos (`GameReport`/`ChampionshipReport`) em páginas A4: capa escura com o placar ou o título,
 * seções com ícone, cartões de indicadores, barras comparativas, colunas, rosca, ranking, tabelas e linha do tempo (as
 * duas últimas quebram de página repetindo o cabeçalho), forma recente, listas e rodapé "página x de y".
 */
final class ReportPdf
{
    private const MARGIN = 40;

    private const BOTTOM = PdfDocument::HEIGHT - 52;

    private const INK = '#0f172a';

    private const MUTED = '#64748b';

    private const LINE = '#e2e8f0';

    private const SOFT = '#f1f5f9';

    private PdfDocument $pdf;

    /** @var array<string, mixed> */
    private array $doc;

    /** @var array<string, string> */
    private array $icons;

    private string $accent;

    private float $y = 0;

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, array{data: string, width: int, height: int}>  $images  escudos já em JPEG (chave = `image` do documento)
     */
    public static function render(array $document, array $images = []): string
    {
        return (new self)->make($document, $images);
    }

    private static function width(): float
    {
        return PdfDocument::WIDTH - 2 * self::MARGIN;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, array{data: string, width: int, height: int}>  $images
     */
    private function make(array $document, array $images): string
    {
        $this->doc = $document;
        $this->icons = $document['icons'] ?? [];
        $this->accent = (string) ($document['accent'] ?? '#2563eb');
        $this->pdf = new PdfDocument($document['title'].' — '.$document['subtitle']);

        foreach ($images as $name => $image) {
            $this->pdf->addJpeg($name, $image['data'], $image['width'], $image['height']);
        }

        $this->pdf->addPage();
        $this->cover();

        foreach ($document['sections'] as $section) {
            $this->section($section);
        }

        $total = $this->pdf->pageCount();

        for ($page = 0; $page < $total; $page++) {
            $this->pdf->onPage($page);
            $this->footer($page + 1, $total);
        }

        return $this->pdf->output();
    }

    // ---- Página ----

    private function cover(): void
    {
        $pdf = $this->pdf;
        $hero = $this->doc['hero'] ?? null;
        $height = $hero !== null ? 250 : 190;
        $pdf->rect(0, 0, PdfDocument::WIDTH, $height, self::INK);
        // formas decorativas translúcidas na cor da organização, recortadas à faixa
        $pdf->clipped(0, 0, PdfDocument::WIDTH, $height, function (PdfDocument $pdf) use ($height) {
            $pdf->withOpacity(0.18, function (PdfDocument $pdf) use ($height) {
                $pdf->polygon([[PdfDocument::WIDTH - 230, 0], [PdfDocument::WIDTH, 0], [PdfDocument::WIDTH, $height], [PdfDocument::WIDTH - 90, $height]], $this->accent);
            });
            $pdf->withOpacity(0.10, function (PdfDocument $pdf) use ($height) {
                $pdf->polygon([[PdfDocument::WIDTH - 330, 0], [PdfDocument::WIDTH - 250, 0], [PdfDocument::WIDTH - 120, $height], [PdfDocument::WIDTH - 200, $height]], $this->accent);
                $pdf->circle(40, $height + 10, 110, $this->accent);
            });
        });
        $pdf->rect(0, $height, PdfDocument::WIDTH, 5, $this->accent);

        $pdf->text(self::MARGIN, 36, mb_strtoupper((string) $this->doc['organization']), 8.5, '#cbd5e1', true);
        $label = mb_strtoupper((string) $this->doc['title']);
        $chip = PdfDocument::width($label, 7.5, true) + 18;
        $pdf->rect(PdfDocument::WIDTH - self::MARGIN - $chip, 24, $chip, 17, $this->accent, null, 8.5);
        $pdf->text(PdfDocument::WIDTH - self::MARGIN - $chip / 2, 35.5, $label, 7.5, '#ffffff', true, 'center');

        if ($hero !== null) {
            $this->hero($hero);
        } else {
            $pdf->text(self::MARGIN, 92, PdfDocument::fit((string) $this->doc['subtitle'], self::width() - 40, 24, true), 24, '#ffffff', true);
            $pdf->text(self::MARGIN, 114, 'Gerado em '.$this->doc['generated_at'], 9, '#94a3b8');
        }

        $this->meta($height - 44);
        $this->y = $height + 28;
    }

    /** @param  array<string, mixed>  $hero */
    private function hero(array $hero): void
    {
        $pdf = $this->pdf;
        $mid = PdfDocument::WIDTH / 2;

        foreach (['home' => $mid - 165, 'away' => $mid + 165] as $side => $cx) {
            $team = $hero[$side];
            $pdf->circle($cx, 100, 34, '#ffffff');
            $pdf->circle($cx, 100, 34, null, $team['color'], 3);

            if ($pdf->hasImage($team['image'])) {
                $pdf->image($team['image'], $cx - 22, 78, 44, 44);
            } else {
                $initials = mb_strtoupper(mb_substr((string) $team['short'], 0, 3));
                $pdf->text($cx, 107, $initials, 18, $team['color'], true, 'center');
            }

            $pdf->text($cx, 154, PdfDocument::fit((string) $team['name'], 170, 12, true), 12, '#ffffff', true, 'center');
        }

        $score = $hero['home']['score'].'  x  '.$hero['away']['score'];
        $pdf->text($mid, 114, $score, 40, '#ffffff', true, 'center');
        $detail = array_filter([$hero['status'], $hero['penalties'] !== null ? 'Pênaltis '.$hero['penalties'] : null, $hero['sets']]);
        $pdf->text($mid, 140, PdfDocument::fit(implode(' · ', $detail), 210, 9), 9, '#cbd5e1', false, 'center');
    }

    private function meta(float $y): void
    {
        $x = self::MARGIN;

        foreach ($this->doc['meta'] as $item) {
            $text = $item['value'];
            $width = min(240, PdfDocument::width($text, 8, true) + 30);

            if ($x + $width > PdfDocument::WIDTH - self::MARGIN) {
                break;
            }

            $this->pdf->withOpacity(0.12, fn (PdfDocument $pdf) => $pdf->rect($x, $y, $width, 22, '#ffffff', null, 11));
            $this->drawIcon($item['icon'], $x + 7, $y + 5, 12, $this->accent);
            $this->pdf->text($x + 23, $y + 14.5, PdfDocument::fit($text, $width - 30, 8, true), 8, '#e2e8f0', true);
            $x += $width + 6;
        }
    }

    private function newPage(): void
    {
        $pdf = $this->pdf;
        $pdf->addPage();
        $pdf->rect(0, 0, PdfDocument::WIDTH, 34, self::INK);
        $pdf->rect(0, 34, PdfDocument::WIDTH, 3, $this->accent);
        $pdf->text(self::MARGIN, 21.5, PdfDocument::fit(mb_strtoupper((string) $this->doc['title']).' · '.$this->doc['subtitle'], self::width(), 8, true), 8, '#e2e8f0', true);
        $this->y = 60;
    }

    private function ensure(float $height): void
    {
        if ($this->y + $height > self::BOTTOM) {
            $this->newPage();
        }
    }

    private function footer(int $page, int $total): void
    {
        $pdf = $this->pdf;
        $y = PdfDocument::HEIGHT - 34;
        $pdf->line(self::MARGIN, $y - 10, PdfDocument::WIDTH - self::MARGIN, $y - 10, self::LINE, 0.6);
        $pdf->text(self::MARGIN, $y, PdfDocument::fit($this->doc['organization'].' · gerado em '.$this->doc['generated_at'], 260, 7.5), 7.5, self::MUTED);

        if ($this->doc['private'] ?? false) {
            $pdf->text(PdfDocument::WIDTH / 2 + 40, $y, 'USO INTERNO', 7.5, self::MUTED, true, 'center');
        }

        $pdf->text(PdfDocument::WIDTH - self::MARGIN, $y, 'Página '.$page.' de '.$total, 7.5, self::MUTED, true, 'right');
    }

    // ---- Seções ----

    /** @param  array<string, mixed>  $section */
    private function section(array $section): void
    {
        $first = $section['blocks'][0] ?? null;
        $this->ensure(46 + min(140, $first !== null ? $this->height($first) : 0));

        $pdf = $this->pdf;
        $pdf->circle(self::MARGIN + 12, $this->y + 12, 12, $this->accent);
        $this->drawIcon($section['icon'], self::MARGIN + 5, $this->y + 5, 14, '#ffffff');
        $pdf->text(self::MARGIN + 32, $this->y + 17, (string) $section['title'], 15, self::INK, true);
        $titleEnd = self::MARGIN + 40 + PdfDocument::width((string) $section['title'], 15, true);
        $pdf->line($titleEnd, $this->y + 12, PdfDocument::WIDTH - self::MARGIN, $this->y + 12, self::LINE, 1);
        $this->y += 38;

        foreach ($section['blocks'] as $block) {
            $this->block($block);
            $this->y += 14;
        }

        $this->y += 8;
    }

    /** @param  array<string, mixed>  $block */
    private function block(array $block): void
    {
        match ($block['type']) {
            'kpis' => $this->kpis($block),
            'versus' => $this->versus($block),
            'columns' => $this->columns($block),
            'donut' => $this->donut($block),
            'ranking' => $this->ranking($block),
            'table' => $this->table($block),
            'timeline' => $this->timeline($block),
            'form' => $this->form($block),
            'list' => $this->listBlock($block),
            default => $this->note($block),
        };
    }

    /** Altura aproximada do bloco (para não deixar o título da seção sozinho no fim da página). @param  array<string, mixed>  $block */
    private function height(array $block): float
    {
        return match ($block['type']) {
            'kpis' => ceil(count($block['items']) / 4) * 74,
            'versus' => 24 + count($block['rows']) * 30,
            'columns' => 210,
            'donut' => 160,
            'ranking' => 20 + count($block['items']) * 26,
            'table' => 40 + min(6, count($block['rows'])) * 18,
            'timeline' => min(6, count($block['items'])) * 28,
            'form' => 20 + count($block['rows']) * 24,
            'list' => 20 + min(4, count($block['items'])) * 30,
            default => 40,
        };
    }

    private function blockTitle(?string $title): void
    {
        if ($title === null || $title === '') {
            return;
        }

        $this->ensure(40);
        $this->pdf->text(self::MARGIN, $this->y + 9, mb_strtoupper($title), 8, self::MUTED, true);
        $this->y += 18;
    }

    /** @param  array<string, mixed>  $block */
    private function kpis(array $block): void
    {
        $pdf = $this->pdf;
        $columns = max(1, min(4, count($block['items'])));
        $gap = 10;
        $width = (self::width() - $gap * ($columns - 1)) / $columns;

        foreach (array_chunk($block['items'], $columns) as $row) {
            $this->ensure(66);

            foreach ($row as $index => $item) {
                $x = self::MARGIN + $index * ($width + $gap);
                $color = $item['color'] ?? $this->accent;
                $pdf->rect($x, $this->y, $width, 64, self::SOFT, null, 8);
                $pdf->rect($x, $this->y, 4, 64, $color, null, 2);
                $pdf->circle($x + 26, $this->y + 22, 12, $color);
                $this->drawIcon($item['icon'], $x + 19, $this->y + 15, 14, '#ffffff');
                $pdf->text($x + 46, $this->y + 29, PdfDocument::fit((string) $item['value'], $width - 54, 19, true), 19, self::INK, true);
                $pdf->text($x + 14, $this->y + 48, PdfDocument::fit((string) $item['label'], $width - 22, 8), 8, self::MUTED);

                if (isset($item['progress'])) {
                    $pdf->rect($x + 14, $this->y + 54, $width - 28, 4, self::LINE, null, 2);
                    $pdf->rect($x + 14, $this->y + 54, ($width - 28) * max(0, min(100, $item['progress'])) / 100, 4, $color, null, 2);
                } elseif (! empty($item['hint'])) {
                    $pdf->text($x + 14, $this->y + 58, PdfDocument::fit((string) $item['hint'], $width - 22, 7), 7, '#94a3b8');
                }
            }

            $this->y += 74;
        }

        $this->y -= 10;
    }

    /** @param  array<string, mixed>  $block */
    private function versus(array $block): void
    {
        $pdf = $this->pdf;
        $this->blockTitle($block['title'] ?? null);
        $this->ensure(24 + 30 * min(3, count($block['rows'])));
        $left = self::MARGIN;
        $right = PdfDocument::WIDTH - self::MARGIN;
        $mid = PdfDocument::WIDTH / 2;
        $pdf->circle($left + 5, $this->y + 6, 5, $block['home']['color']);
        $pdf->text($left + 15, $this->y + 10, PdfDocument::fit((string) $block['home']['name'], 200, 10, true), 10, self::INK, true);
        $pdf->circle($right - 5, $this->y + 6, 5, $block['away']['color']);
        $pdf->text($right - 15, $this->y + 10, PdfDocument::fit((string) $block['away']['name'], 200, 10, true), 10, self::INK, true, 'right');
        $this->y += 24;
        $track = $mid - $left - 40;

        foreach ($block['rows'] as $row) {
            $this->ensure(30);
            $max = max(1, $row['home'], $row['away']);
            $suffix = $row['suffix'] ?? '';
            $pdf->text($mid, $this->y + 8, (string) $row['label'], 8.5, self::MUTED, true, 'center');
            $pdf->text($left, $this->y + 22, $row['home'].$suffix, 12, $row['home'] >= $row['away'] ? self::INK : self::MUTED, true);
            $pdf->text($right, $this->y + 22, $row['away'].$suffix, 12, $row['away'] >= $row['home'] ? self::INK : self::MUTED, true, 'right');
            $pdf->rect($mid - 4 - $track, $this->y + 14, $track, 8, self::SOFT, null, 4);
            $pdf->rect($mid + 4, $this->y + 14, $track, 8, self::SOFT, null, 4);
            $home = $track * $row['home'] / $max;
            $away = $track * $row['away'] / $max;
            $pdf->rect($mid - 4 - $home, $this->y + 14, $home, 8, $block['home']['color'], null, 4);
            $pdf->rect($mid + 4, $this->y + 14, $away, 8, $block['away']['color'], null, 4);
            $this->y += 30;
        }
    }

    /** @param  array<string, mixed>  $block */
    private function columns(array $block): void
    {
        $pdf = $this->pdf;
        $this->ensure(210);
        $this->blockTitle($block['title'] ?? null);
        $chartTop = $this->y;
        $chartHeight = 140;
        $axis = 26;
        $left = self::MARGIN + $axis;
        $width = self::width() - $axis;
        $values = array_merge(...array_map(fn (array $category) => $category['values'], $block['categories'] ?: [['values' => [0]]]));
        $max = self::niceMax((float) max(1, ...$values));

        for ($step = 0; $step <= 4; $step++) {
            $y = $chartTop + $chartHeight - $chartHeight * $step / 4;
            $pdf->line($left, $y, $left + $width, $y, $step === 0 ? '#cbd5e1' : self::LINE, $step === 0 ? 1 : 0.5);
            $pdf->text($left - 6, $y + 3, self::short($max * $step / 4), 7, '#94a3b8', false, 'right');
        }

        $count = max(1, count($block['categories']));
        $group = $width / $count;
        $series = max(1, count($block['series']));
        $bar = min(22, $group * 0.72 / $series);

        foreach ($block['categories'] as $index => $category) {
            $start = $left + $index * $group + ($group - $bar * $series) / 2;

            foreach ($category['values'] as $s => $value) {
                $height = $chartHeight * $value / $max;
                $x = $start + $s * $bar;
                $pdf->rect($x + 1, $chartTop + $chartHeight - $height, $bar - 2, $height, $block['series'][$s]['color'] ?? $this->accent, null, min(3, ($bar - 2) / 2));

                if ($bar >= 9 && $value > 0) {
                    $pdf->text($x + $bar / 2, $chartTop + $chartHeight - $height - 3, self::short($value), 6.5, self::INK, true, 'center');
                }
            }

            $pdf->text($left + $index * $group + $group / 2, $chartTop + $chartHeight + 12, PdfDocument::fit((string) $category['label'], $group - 2, 7), 7, self::MUTED, false, 'center');
        }

        $x = self::MARGIN + $axis;
        $legendY = $chartTop + $chartHeight + 28;

        foreach ($block['series'] as $item) {
            $pdf->rect($x, $legendY - 7, 9, 9, $item['color'], null, 2);
            $pdf->text($x + 13, $legendY, (string) $item['label'], 8, self::INK);
            $x += 26 + PdfDocument::width((string) $item['label'], 8);
        }

        $this->y = $legendY + 6;
    }

    /** @param  array<string, mixed>  $block */
    private function donut(array $block): void
    {
        $pdf = $this->pdf;
        $this->ensure(160);
        $this->blockTitle($block['title'] ?? null);
        $cx = self::MARGIN + 70;
        $cy = $this->y + 66;
        $total = max(1, $block['total']);
        $from = 0.0;

        if ($block['parts'] === []) {
            $pdf->circle($cx, $cy, 62, self::LINE);
        }

        foreach ($block['parts'] as $part) {
            $to = $from + $part['value'] / $total;
            $pdf->wedge($cx, $cy, 62, $from, $to, $part['color']);
            $from = $to;
        }

        $pdf->circle($cx, $cy, 38, '#ffffff');
        $pdf->text($cx, $cy + 4, self::short($block['total']), 18, self::INK, true, 'center');
        $pdf->text($cx, $cy + 16, (string) $block['total_label'], 7.5, self::MUTED, false, 'center');

        $x = self::MARGIN + 170;
        $right = PdfDocument::WIDTH - self::MARGIN;
        $y = $this->y + 66 - count($block['parts']) * 11;

        foreach ($block['parts'] as $part) {
            $percent = (int) round($part['value'] / $total * 100);
            $pdf->rect($x, $y + 3, 10, 10, $part['color'], null, 2);
            $pdf->text($x + 16, $y + 12, PdfDocument::fit((string) $part['label'], 190, 9), 9, self::INK);
            $pdf->text($right - 46, $y + 12, self::short($part['value']), 9.5, self::INK, true, 'right');
            $pdf->rect($right - 38, $y + 2, 38, 13, self::SOFT, null, 6.5);
            $pdf->text($right - 19, $y + 11.5, $percent.'%', 7.5, self::MUTED, true, 'center');
            $pdf->line($x, $y + 19, $right, $y + 19, self::LINE, 0.5);
            $y += 22;
        }

        $this->y += 136;
    }

    /** @param  array<string, mixed>  $block */
    private function ranking(array $block): void
    {
        $pdf = $this->pdf;
        $this->blockTitle($block['title'] ?? null);
        $max = max(1, ...array_column($block['items'], 'value'));
        $barLeft = self::MARGIN + 250;
        $barWidth = self::width() - 250 - 50;

        foreach ($block['items'] as $index => $item) {
            $this->ensure(26);
            $color = $item['color'] ?? $this->accent;

            if ($index % 2 === 0) {
                $pdf->rect(self::MARGIN, $this->y, self::width(), 24, '#f8fafc', null, 4);
            }

            $pdf->circle(self::MARGIN + 13, $this->y + 12, 9, $item['position'] <= 3 ? $this->accent : self::LINE);
            $pdf->text(self::MARGIN + 13, $this->y + 15, (string) $item['position'], 8, $item['position'] <= 3 ? '#ffffff' : self::INK, true, 'center');
            $pdf->text(self::MARGIN + 30, $this->y + 11, PdfDocument::fit((string) $item['label'], 210, 9.5, true), 9.5, self::INK, true);

            if (! empty($item['sub'])) {
                $pdf->text(self::MARGIN + 30, $this->y + 20.5, PdfDocument::fit((string) $item['sub'], 210, 7.5), 7.5, self::MUTED);
            }

            $pdf->rect($barLeft, $this->y + 8, $barWidth, 8, self::SOFT, null, 4);
            $pdf->rect($barLeft, $this->y + 8, $barWidth * $item['value'] / $max, 8, $color, null, 4);
            $pdf->text(PdfDocument::WIDTH - self::MARGIN - 6, $this->y + 15.5, self::short($item['value']), 11, self::INK, true, 'right');
            $this->y += 26;
        }
    }

    /** @param  array<string, mixed>  $block */
    private function table(array $block): void
    {
        $pdf = $this->pdf;
        $this->ensure(60);
        $this->blockTitle($block['title'] ?? null);
        $weights = array_sum(array_column($block['columns'], 'width')) ?: 1;
        $positions = [];
        $x = self::MARGIN;

        foreach ($block['columns'] as $column) {
            $width = self::width() * $column['width'] / $weights;
            $positions[] = ['x' => $x, 'width' => $width] + $column;
            $x += $width;
        }

        $header = function () use ($pdf, $positions) {
            $pdf->rect(self::MARGIN, $this->y, self::width(), 20, self::INK, null, 4);

            foreach ($positions as $column) {
                $this->cell($column, (string) $column['label'], $this->y + 13.5, 7.5, '#ffffff', true);
            }

            $this->y += 20;
        };

        $header();

        foreach ($block['rows'] as $index => $row) {
            if ($this->y + 18 > self::BOTTOM) {
                $this->newPage();
                $header();
            }

            if ($row['highlight']) {
                $pdf->withOpacity(0.13, fn (PdfDocument $pdf) => $pdf->rect(self::MARGIN, $this->y, self::width(), 18, $this->accent));
                $pdf->rect(self::MARGIN, $this->y, 3, 18, $this->accent);
            } elseif ($index % 2 === 1) {
                $pdf->rect(self::MARGIN, $this->y, self::width(), 18, '#f8fafc');
            }

            foreach ($positions as $column) {
                $this->cell($column, (string) ($row['cells'][$column['key']] ?? ''), $this->y + 12.5, 8.5, self::INK, $row['highlight']);
            }

            $pdf->line(self::MARGIN, $this->y + 18, PdfDocument::WIDTH - self::MARGIN, $this->y + 18, self::LINE, 0.4);
            $this->y += 18;
        }
    }

    /** @param  array<string, mixed>  $column */
    private function cell(array $column, string $text, float $y, float $size, string $color, bool $bold): void
    {
        $text = PdfDocument::fit($text, $column['width'] - 10, $size, $bold);
        $x = match ($column['align']) {
            'center' => $column['x'] + $column['width'] / 2,
            'right' => $column['x'] + $column['width'] - 6,
            default => $column['x'] + 6,
        };

        $this->pdf->text($x, $y, $text, $size, $color, $bold, $column['align'] === 'center' ? 'center' : ($column['align'] === 'right' ? 'right' : 'left'));
    }

    /** @param  array<string, mixed>  $block */
    private function timeline(array $block): void
    {
        $pdf = $this->pdf;
        $mid = PdfDocument::WIDTH / 2;
        $sideWidth = $mid - self::MARGIN - 34;

        foreach ($block['items'] as $item) {
            if ($this->y + 28 > self::BOTTOM) {
                $this->newPage();
            }

            $pdf->line($mid, $this->y, $mid, $this->y + 28, self::LINE, 2);

            if ($item['side'] === null) {
                $label = PdfDocument::fit((string) $item['title'].(! empty($item['detail']) ? ' · '.$item['detail'] : ''), 300, 8, true);
                $width = PdfDocument::width($label, 8, true) + 34;
                $pdf->rect($mid - $width / 2, $this->y + 5, $width, 18, self::SOFT, self::LINE, 9, 0.6);
                $this->drawIcon($item['icon'], $mid - $width / 2 + 8, $this->y + 8, 12, $item['color']);
                $pdf->text($mid - $width / 2 + 25, $this->y + 17, $label, 8, self::MUTED, true);
                $this->y += 28;

                continue;
            }

            $pdf->rect($mid - 18, $this->y + 6, 36, 16, self::INK, null, 8);
            $pdf->text($mid, $this->y + 17, $item['minute'] ?? '—', 8, '#ffffff', true, 'center');
            $home = $item['side'] === 'home';
            $iconX = $home ? $mid - 42 : $mid + 26;
            $pdf->circle($iconX + 8, $this->y + 14, 9, $item['color']);
            $this->drawIcon($item['icon'], $iconX + 2, $this->y + 8, 12, '#ffffff');
            $textX = $home ? $mid - 48 : $mid + 48;
            $align = $home ? 'right' : 'left';
            $pdf->text($textX, $this->y + 12, PdfDocument::fit((string) $item['title'], $sideWidth - 20, 9, true), 9, self::INK, true, $align);

            if (! empty($item['detail'])) {
                $pdf->text($textX, $this->y + 22, PdfDocument::fit((string) $item['detail'], $sideWidth - 20, 7.5), 7.5, self::MUTED, false, $align);
            }

            $this->y += 28;
        }
    }

    /** @param  array<string, mixed>  $block */
    private function form(array $block): void
    {
        $pdf = $this->pdf;
        $this->blockTitle($block['title'] ?? null);
        $colors = ['V' => '#16a34a', 'E' => '#94a3b8', 'D' => '#dc2626'];

        foreach ($block['rows'] as $row) {
            $this->ensure(24);
            $pdf->circle(self::MARGIN + 6, $this->y + 10, 5, $row['color']);
            $pdf->text(self::MARGIN + 18, $this->y + 13.5, PdfDocument::fit((string) $row['label'], 250, 9.5, true), 9.5, self::INK, true);

            if ($row['results'] === []) {
                $pdf->text(PdfDocument::WIDTH - self::MARGIN, $this->y + 13.5, 'sem jogos', 8, self::MUTED, false, 'right');
            }

            foreach (array_values($row['results']) as $index => $result) {
                $cx = PdfDocument::WIDTH - self::MARGIN - 10 - (count($row['results']) - 1 - $index) * 24;
                $pdf->circle($cx, $this->y + 10, 9, $colors[$result] ?? '#94a3b8');
                $pdf->text($cx, $this->y + 13, $result, 8, '#ffffff', true, 'center');
            }

            $pdf->line(self::MARGIN, $this->y + 22, PdfDocument::WIDTH - self::MARGIN, $this->y + 22, self::LINE, 0.4);
            $this->y += 24;
        }

        $this->ensure(14);
        $pdf->text(PdfDocument::WIDTH - self::MARGIN, $this->y + 10, 'V vitória · E empate · D derrota (mais antigo à esquerda)', 7, '#94a3b8', false, 'right');
        $this->y += 12;
    }

    /** @param  array<string, mixed>  $block */
    private function listBlock(array $block): void
    {
        $pdf = $this->pdf;
        $this->blockTitle($block['title'] ?? null);

        foreach ($block['items'] as $item) {
            $this->ensure(30);
            $pdf->rect(self::MARGIN, $this->y, self::width(), 26, self::SOFT, null, 6);
            $color = $item['color'] ?? $this->accent;
            $textX = self::MARGIN + 12;

            if (! empty($item['icon'])) {
                $pdf->circle(self::MARGIN + 17, $this->y + 13, 9, $color);
                $this->drawIcon($item['icon'], self::MARGIN + 11, $this->y + 7, 12, '#ffffff');
                $textX = self::MARGIN + 34;
            }

            $pdf->text($textX, $this->y + 11.5, PdfDocument::fit((string) $item['title'], 360, 9, true), 9, self::INK, true);

            if (! empty($item['detail'])) {
                $pdf->text($textX, $this->y + 21, PdfDocument::fit((string) $item['detail'], 360, 7.5), 7.5, self::MUTED);
            }

            if (! empty($item['value'])) {
                $width = PdfDocument::width((string) $item['value'], 10, true) + 18;
                $pdf->rect(PdfDocument::WIDTH - self::MARGIN - $width - 6, $this->y + 5, $width, 16, self::INK, null, 8);
                $pdf->text(PdfDocument::WIDTH - self::MARGIN - 6 - $width / 2, $this->y + 16.5, (string) $item['value'], 10, '#ffffff', true, 'center');
            }

            $this->y += 30;
        }

        $this->y -= 4;
    }

    /** @param  array<string, mixed>  $block */
    private function note(array $block): void
    {
        $lines = PdfDocument::wrap((string) ($block['text'] ?? ''), self::width() - 40, 9);
        $height = 14 + count($lines) * 13;
        $this->ensure($height);
        $this->pdf->rect(self::MARGIN, $this->y, self::width(), $height, '#f8fafc', self::LINE, 6, 0.6);
        $this->drawIcon('list', self::MARGIN + 10, $this->y + $height / 2 - 6, 12, '#94a3b8');

        foreach ($lines as $index => $line) {
            $this->pdf->text(self::MARGIN + 30, $this->y + 16 + $index * 13, $line, 9, self::MUTED);
        }

        $this->y += $height;
    }

    private function drawIcon(string $key, float $x, float $y, float $size, string $color): void
    {
        if (isset($this->icons[$key])) {
            $this->pdf->icon($this->icons[$key], $x, $y, $size, $color);
        }
    }

    /** Número curto: inteiro sem casas, fração com uma casa e vírgula. */
    private static function short(float|int $value): string
    {
        return floor((float) $value) === (float) $value ? number_format((float) $value, 0, ',', '.') : number_format((float) $value, 1, ',', '.');
    }

    /** Teto "redondo" do eixo (1, 2, 2.5, 5 x 10^n), para as linhas de grade terem números legíveis. */
    public static function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 1;
        }

        if ($value <= 4) {
            return 4; // grade de 1 em 1
        }

        $power = 10 ** floor(log10($value));

        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($value <= $step * $power) {
                return max(4, $step * $power);
            }
        }

        return 10 * $power;
    }
}

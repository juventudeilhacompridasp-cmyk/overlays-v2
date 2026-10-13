<?php

namespace App\Modules\Matches\Reading;

use SimpleXMLElement;
use ZipArchive;

/** Lê CSV e XLSX como lista de linhas de texto, sem biblioteca externa (o XLSX é um zip de XML). */
final class SpreadsheetRows
{
    private const MAX_XML_BYTES = 20 * 1024 * 1024;

    private const MAX_ROWS = 2000;

    /**
     * @return list<list<string>>
     *
     * @throws SheetReadFailed
     */
    public static function fromCsv(string $contents): array
    {
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'ISO-8859-1');
        }

        $firstLine = strtok($contents, "\n") ?: '';
        $delimiter = collect([';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")])
            ->sortDesc()
            ->keys()
            ->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $rows = [];

        while (($row = fgetcsv($stream, 0, (string) $delimiter, '"', '')) !== false && count($rows) < self::MAX_ROWS) {
            $row = array_map(fn ($cell) => trim((string) $cell), $row);

            if (array_filter($row, fn ($cell) => $cell !== '') !== []) {
                $rows[] = $row;
            }
        }

        fclose($stream);

        return $rows;
    }

    /**
     * Primeira planilha do arquivo.
     *
     * @return list<list<string>>
     *
     * @throws SheetReadFailed
     */
    public static function fromXlsx(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $contents);

        try {
            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw new SheetReadFailed('Não foi possível abrir a planilha XLSX.');
            }

            try {
                $shared = self::sharedStrings($zip);
                $sheetName = self::firstSheet($zip);

                return self::sheetRows(self::xml($zip, $sheetName), $shared);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $strings = [];

        foreach (self::xml($zip, 'xl/sharedStrings.xml')->si as $item) {
            $strings[] = self::text($item);
        }

        return $strings;
    }

    private static function firstSheet(ZipArchive $zip): string
    {
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $names[] = $name;
            }
        }

        natsort($names);

        if ($names === []) {
            throw new SheetReadFailed('A planilha XLSX não tem nenhuma aba.');
        }

        return (string) reset($names);
    }

    private static function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stat = $zip->statName($name);

        if ($stat === false || $stat['size'] > self::MAX_XML_BYTES) {
            throw new SheetReadFailed('A planilha XLSX é grande demais ou está corrompida.');
        }

        $xml = simplexml_load_string((string) $zip->getFromName($name), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

        if ($xml === false) {
            throw new SheetReadFailed('A planilha XLSX está corrompida.');
        }

        return $xml;
    }

    /** Texto de um `<si>`/`<is>` (simples ou em partes formatadas). */
    private static function text(SimpleXMLElement $node): string
    {
        $text = (string) $node->t;

        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /**
     * @param  list<string>  $shared
     * @return list<list<string>>
     */
    private static function sheetRows(SimpleXMLElement $sheet, array $shared): array
    {
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $index = self::columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];

                $cells[$index] = trim(match ($type) {
                    's' => $shared[(int) $cell->v] ?? '',
                    'inlineStr' => self::text($cell->is),
                    default => (string) $cell->v,
                });
            }

            if ($cells === [] || array_filter($cells, fn ($cell) => $cell !== '') === []) {
                continue;
            }

            $line = [];

            for ($i = 0; $i <= max(array_keys($cells)); $i++) {
                $line[] = $cells[$i] ?? '';
            }

            $rows[] = $line;

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        return $rows;
    }

    /** "C7" → 2 (coluna C). */
    private static function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $match);
        $index = 0;

        foreach (str_split($match[0] ?? 'A') as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}

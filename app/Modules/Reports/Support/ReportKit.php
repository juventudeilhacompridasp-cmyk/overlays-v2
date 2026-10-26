<?php

namespace App\Modules\Reports\Support;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Teams\Models\Person;

/**
 * Peças comuns dos relatórios: os blocos (o documento é só DADO — o PDF e as telas desenham o mesmo bloco), cores,
 * unidade do placar e nomes. Bloco novo = construtor aqui + desenho em `ReportPdf` + `Components/Report/ReportBlock.tsx`.
 */
final class ReportKit
{
    public const BLOCKS = ['kpis', 'versus', 'columns', 'donut', 'ranking', 'table', 'timeline', 'form', 'list', 'note'];

    /** Cores das séries quando a equipe não tem cor (e para distinguir duas equipes de mesma cor). */
    public const PALETTE = ['#2563eb', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

    public const WIN = '#16a34a';

    public const DRAW = '#94a3b8';

    public const LOSS = '#dc2626';

    /** @return array{plural: string, singular: string, label: string} */
    public static function unit(string $scoreModel): array
    {
        return match ($scoreModel) {
            'points' => ['plural' => 'pontos', 'singular' => 'ponto', 'label' => 'Pontos'],
            'sets' => ['plural' => 'sets', 'singular' => 'set', 'label' => 'Sets'],
            default => ['plural' => 'gols', 'singular' => 'gol', 'label' => 'Gols'],
        };
    }

    public static function color(?string $color, int $fallback = 0): string
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : self::PALETTE[$fallback % count(self::PALETTE)];
    }

    /** @return array{0: string, 1: string} cores do mandante e do visitante, nunca iguais */
    public static function pair(?string $home, ?string $away): array
    {
        $a = self::color($home, 0);
        $b = self::color($away, 1);

        return [$a, $a === $b ? self::PALETTE[$a === self::PALETTE[1] ? 3 : 1] : $b];
    }

    public static function accent(): string
    {
        return PublicTheme::current()['primary'];
    }

    public static function organization(): string
    {
        return PublicTheme::current()['name'];
    }

    /** Nome no relatório: público (overlay) passa por PublicName; o PDF interno usa apelido ou nome. */
    public static function name(?Person $person, bool $public): ?string
    {
        if ($person === null) {
            return null;
        }

        return $public ? PublicName::of($person) : ($person->nickname ?: $person->name);
    }

    public static function team(?Entry $entry): string
    {
        return $entry?->team->name ?? 'A definir';
    }

    public static function short(?Entry $entry): string
    {
        $team = $entry?->team;

        return $team === null ? 'A definir' : ($team->short_name ?: self::shorten($team->name));
    }

    /** Nome curto para legendas: palavras inteiras até 16 letras, sem terminar em "do", "da", "d'Oeste"... */
    public static function shorten(string $name, int $max = 16): string
    {
        if (mb_strlen($name) <= $max) {
            return $name;
        }

        $out = [];

        foreach (preg_split('/\s+/u', trim($name)) ?: [] as $word) {
            if (mb_strlen(implode(' ', [...$out, $word])) > $max) {
                break;
            }

            $out[] = $word;
        }

        while ($out !== [] && preg_match("/^(d[aeo]s?|e|d'.*)$/iu", (string) end($out)) === 1) {
            array_pop($out);
        }

        return $out === [] ? mb_substr($name, 0, $max - 1).'.' : implode(' ', $out);
    }

    /** "V", "E" ou "D" do ponto de vista da inscrição. */
    public static function result(Game $game, string $entryId): string
    {
        [$for, $against] = $game->home_entry_id === $entryId ? [$game->home_score, $game->away_score] : [$game->away_score, $game->home_score];

        return $for > $against ? 'V' : ($for === $against ? 'E' : 'D');
    }

    public static function number(float|int $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    // ---- Blocos ----

    /** @param  list<array{icon: string, label: string, value: string, hint?: string|null, color?: string|null, progress?: int|null}>  $items */
    public static function kpis(array $items): array
    {
        return ['type' => 'kpis', 'items' => $items];
    }

    /**
     * Barras do comparativo: cada linha cresce do centro para o lado da equipe.
     *
     * @param  array{name: string, color: string}  $home
     * @param  array{name: string, color: string}  $away
     * @param  list<array{label: string, home: int|float, away: int|float, suffix?: string}>  $rows
     */
    public static function versus(array $home, array $away, array $rows, ?string $title = null): array
    {
        return ['type' => 'versus', 'title' => $title, 'home' => $home, 'away' => $away, 'rows' => $rows];
    }

    /**
     * Colunas agrupadas: uma série por cor, uma categoria por grupo de colunas.
     *
     * @param  list<array{label: string, color: string}>  $series
     * @param  list<array{label: string, values: list<int|float>}>  $categories
     */
    public static function columns(string $title, array $series, array $categories, string $unit = ''): array
    {
        return ['type' => 'columns', 'title' => $title, 'unit' => $unit, 'series' => $series, 'categories' => $categories];
    }

    /** @param  list<array{label: string, value: int|float, color: string}>  $parts */
    public static function donut(string $title, array $parts, string $totalLabel): array
    {
        $parts = array_values(array_filter($parts, fn (array $part) => $part['value'] > 0));

        return ['type' => 'donut', 'title' => $title, 'total' => array_sum(array_column($parts, 'value')), 'total_label' => $totalLabel, 'parts' => $parts];
    }

    /** @param  list<array{label: string, sub?: string|null, value: int|float, color?: string|null}>  $items */
    public static function ranking(string $title, array $items, string $unit = ''): array
    {
        $position = 0;
        $last = null;
        $out = [];

        foreach ($items as $index => $item) {
            // empate divide a posição (dois com 5 gols = os dois em 1º)
            $position = $last !== null && $item['value'] === $last ? $position : $index + 1;
            $last = $item['value'];
            $out[] = ['position' => $position, 'label' => $item['label'], 'sub' => $item['sub'] ?? null, 'value' => $item['value'], 'color' => $item['color'] ?? null];
        }

        return ['type' => 'ranking', 'title' => $title, 'unit' => $unit, 'items' => $out];
    }

    /**
     * @param  list<array{key: string, label: string, align?: string, width?: int}>  $columns
     * @param  list<array{cells: array<string, string|int|float|null>, highlight?: bool, color?: string|null}>  $rows
     */
    public static function table(?string $title, array $columns, array $rows): array
    {
        return [
            'type' => 'table',
            'title' => $title,
            'columns' => array_map(fn (array $column) => ['key' => $column['key'], 'label' => $column['label'], 'align' => $column['align'] ?? 'left', 'width' => $column['width'] ?? 1], $columns),
            'rows' => array_map(fn (array $row) => ['cells' => array_map(fn ($value) => $value === null ? '—' : (string) $value, $row['cells']), 'highlight' => $row['highlight'] ?? false, 'color' => $row['color'] ?? null], $rows),
        ];
    }

    /** @param  list<array{minute: string|null, period: string|null, side: string|null, icon: string, color: string, title: string, detail: string|null}>  $items */
    public static function timeline(array $items): array
    {
        return ['type' => 'timeline', 'items' => $items];
    }

    /** @param  list<array{label: string, color: string, results: list<string>}>  $rows */
    public static function form(string $title, array $rows): array
    {
        return ['type' => 'form', 'title' => $title, 'rows' => $rows];
    }

    /** @param  list<array{title: string, detail?: string|null, value?: string|null, icon?: string|null, color?: string|null}>  $items */
    public static function list(string $title, array $items): array
    {
        return ['type' => 'list', 'title' => $title, 'items' => array_map(fn (array $item) => ['title' => $item['title'], 'detail' => $item['detail'] ?? null, 'value' => $item['value'] ?? null, 'icon' => $item['icon'] ?? null, 'color' => $item['color'] ?? null], $items)];
    }

    public static function note(string $text): array
    {
        return ['type' => 'note', 'text' => $text];
    }

    /**
     * Fecha o documento: seção sem bloco ganha um aviso e os ícones usados vão junto (as telas não precisam pedir).
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function finish(array $document): array
    {
        $document['sections'] = array_map(fn (array $section) => $section['blocks'] === [] ? [...$section, 'blocks' => [self::note('Sem dados para esta seção ainda.')]] : $section, $document['sections']);

        $icons = [];
        array_walk_recursive($document, function ($value, $key) use (&$icons) {
            if ($key === 'icon' && is_string($value)) {
                $icons[] = $value;
            }
        });

        $document['icons'] = ReportIcons::for($icons);

        return $document;
    }
}

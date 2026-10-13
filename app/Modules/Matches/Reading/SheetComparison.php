<?php

namespace App\Modules\Matches\Reading;

/**
 * Conferência pura entre a súmula lida (já revisada por uma pessoa) e o que o sistema tem do jogo (ADR 0004).
 * Nunca escolhe um lado nem altera o jogo: só classifica cada item e mostra os dois valores.
 *
 * Status: `match` (confere), `differs` (diverge), `only_sheet` (está na súmula e não no sistema),
 * `only_system` (está no sistema e não na súmula), `unclear` (a súmula não trouxe o dado).
 *
 * @phpstan-type Item array{section: string, label: string, status: string, sheet: ?string, system: ?string, note: ?string}
 * @phpstan-type Row array{type?: string, team: string, name: ?string, shirt: ?int, starter?: ?bool, minute?: ?string, detail?: ?string}
 * @phpstan-type Facts array{home_team: ?string, away_team: ?string, home_score: int, away_score: int, lineups: list<Row>, events: list<Row>}
 */
final class SheetComparison
{
    public const STATUSES = ['match', 'differs', 'only_sheet', 'only_system', 'unclear'];

    /** Tipos de lance comparados um a um (os demais, como pontos de rally, entram só no placar). */
    private const EVENT_TYPES = ['goal', 'own_goal', 'yellow_card', 'red_card', 'substitution', 'foul', 'timeout'];

    private const EVENT_LABELS = [
        'goal' => 'Gols',
        'own_goal' => 'Gols contra',
        'yellow_card' => 'Cartões amarelos',
        'red_card' => 'Cartões vermelhos',
        'substitution' => 'Substituições',
        'foul' => 'Faltas',
        'timeout' => 'Pedidos de tempo',
    ];

    /**
     * @param  array<string, mixed>  $sheet  `ExtractedSheet::toArray()`
     * @param  Facts  $facts
     * @return list<Item>
     */
    public function compare(array $sheet, array $facts): array
    {
        $items = [...$this->score($sheet, $facts), ...$this->teams($sheet, $facts)];

        foreach (['home', 'away'] as $side) {
            $team = $this->teamName($facts, $side);
            $items = [...$items, ...$this->lineups($side, $team, (array) ($sheet['lineups'] ?? []), $facts['lineups'])];
        }

        foreach (self::EVENT_TYPES as $type) {
            foreach (['home', 'away'] as $side) {
                $items = [...$items, ...$this->events($type, $side, $this->teamName($facts, $side), (array) ($sheet['events'] ?? []), $facts['events'])];
            }
        }

        return $items;
    }

    /**
     * @param  list<Item>  $items
     * @return array<string, int>
     */
    public static function summary(array $items): array
    {
        $summary = array_fill_keys(self::STATUSES, 0);

        foreach ($items as $item) {
            $summary[$item['status']]++;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @param  Facts  $facts
     * @return list<Item>
     */
    private function score(array $sheet, array $facts): array
    {
        $items = [];

        foreach (['home' => 'Placar da casa', 'away' => 'Placar do visitante'] as $side => $label) {
            $read = $sheet[$side.'_score'] ?? null;
            $system = $facts[$side.'_score'];

            $items[] = $this->item('Placar', $label, $read === null ? 'unclear' : ((int) $read === $system ? 'match' : 'differs'), $read === null ? null : (string) $read, (string) $system, $read === null ? 'A súmula não trouxe este dado.' : null);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @param  Facts  $facts
     * @return list<Item>
     */
    private function teams(array $sheet, array $facts): array
    {
        $items = [];

        foreach (['home' => 'Equipe da casa', 'away' => 'Equipe visitante'] as $side => $label) {
            $read = $sheet[$side.'_team'] ?? null;
            $system = $facts[$side.'_team'];

            $items[] = $this->item('Equipes', $label, $read === null ? 'unclear' : (NameMatcher::same($read, $system) ? 'match' : 'differs'), $read, $system, null);
        }

        return $items;
    }

    /**
     * @param  array<int, mixed>  $sheetRows
     * @param  list<Row>  $systemRows
     * @return list<Item>
     */
    private function lineups(string $side, string $team, array $sheetRows, array $systemRows): array
    {
        $section = "Escalação — {$team}";
        $sheetRows = array_values(array_filter($sheetRows, fn ($row) => is_array($row) && $row['team'] === $side));
        $systemRows = array_values(array_filter($systemRows, fn ($row) => $row['team'] === $side));

        if ($systemRows === []) {
            return $sheetRows === [] ? [] : [$this->item($section, 'Escalação', 'only_sheet', count($sheetRows).' atleta(s) na súmula', null, 'O jogo não tem escalação lançada no sistema.')];
        }

        $items = [];
        [$pairs, $onlySheet, $onlySystem] = $this->pair($sheetRows, $systemRows);

        foreach ($pairs as [$s, $y]) {
            $problems = [];

            if ($s['shirt'] !== null && $y['shirt'] !== null && $s['shirt'] !== $y['shirt']) {
                $problems[] = 'camisa diferente';
            }

            if ($s['name'] !== null && $y['name'] !== null && ! NameMatcher::same($s['name'], $y['name'])) {
                $problems[] = 'nome diferente';
            }

            if (is_bool($s['starter'] ?? null) && is_bool($y['starter'] ?? null) && $s['starter'] !== $y['starter']) {
                $problems[] = 'titular × reserva';
            }

            $items[] = $this->item($section, $this->person($y), $problems === [] ? 'match' : 'differs', $this->describe($s, true), $this->describe($y, true), $problems === [] ? null : ucfirst(implode(', ', $problems)).'.');
        }

        foreach ($onlySheet as $s) {
            $items[] = $this->item($section, $this->person($s), 'only_sheet', $this->describe($s, true), null, 'Na súmula, mas não na escalação do sistema.');
        }

        foreach ($onlySystem as $y) {
            $items[] = $this->item($section, $this->person($y), 'only_system', null, $this->describe($y, true), 'Na escalação do sistema, mas não na súmula.');
        }

        return $items;
    }

    /**
     * @param  array<int, mixed>  $sheetRows
     * @param  list<Row>  $systemRows
     * @return list<Item>
     */
    private function events(string $type, string $side, string $team, array $sheetRows, array $systemRows): array
    {
        $section = self::EVENT_LABELS[$type].' — '.$team;
        $sheetRows = array_values(array_filter($sheetRows, fn ($row) => is_array($row) && $row['type'] === $type && $row['team'] === $side));
        $systemRows = array_values(array_filter($systemRows, fn ($row) => ($row['type'] ?? null) === $type && $row['team'] === $side));

        $items = [];
        [$pairs, $onlySheet, $onlySystem] = $this->pair($sheetRows, $systemRows, anonymousMatchesAny: true);

        foreach ($pairs as [$s, $y]) {
            $problems = [];

            if ($s['shirt'] !== null && $y['shirt'] !== null && $s['shirt'] !== $y['shirt']) {
                $problems[] = 'camisa diferente';
            }

            if ($type === 'substitution' && ($s['detail'] ?? null) !== null && ($y['detail'] ?? null) !== null && ! NameMatcher::same($s['detail'], $y['detail'])) {
                $problems[] = 'quem saiu é diferente';
            }

            $note = $problems === [] ? ($y['name'] === null && $y['shirt'] === null && $type !== 'timeout' ? 'O sistema não tem o atleta deste lance; só a quantidade confere.' : null) : ucfirst(implode(', ', $problems)).'.';

            $items[] = $this->item($section, self::EVENT_LABELS[$type], $problems === [] ? 'match' : 'differs', $this->describe($s, false), $this->describe($y, false), $note);
        }

        foreach ($onlySheet as $s) {
            $items[] = $this->item($section, self::EVENT_LABELS[$type], 'only_sheet', $this->describe($s, false), null, 'Na súmula, mas não lançado no sistema.');
        }

        foreach ($onlySystem as $y) {
            $items[] = $this->item($section, self::EVENT_LABELS[$type], 'only_system', null, $this->describe($y, false), 'Lançado no sistema, mas não consta na súmula.');
        }

        return $items;
    }

    /**
     * Junta linhas da súmula com as do sistema: primeiro pela camisa, depois pelo nome, depois (lances sem atleta) pela ordem.
     * Ambíguo nunca é adivinhado: a linha sobra e aparece como "só na súmula" / "só no sistema".
     *
     * @param  list<array<string, mixed>>  $sheetRows
     * @param  list<Row>  $systemRows
     * @return array{0: list<array{0: array<string, mixed>, 1: Row}>, 1: list<array<string, mixed>>, 2: list<Row>}
     */
    private function pair(array $sheetRows, array $systemRows, bool $anonymousMatchesAny = false): array
    {
        $pairs = [];
        $left = [];

        foreach ($sheetRows as $s) {
            $found = null;

            if ($s['shirt'] !== null) {
                [$found, $systemRows] = $this->take($systemRows, fn ($y) => $y['shirt'] === $s['shirt']);
            }

            if ($found === null && $s['name'] !== null) {
                [$found, $systemRows] = $this->take($systemRows, fn ($y) => NameMatcher::same($s['name'], $y['name']));
            }

            if ($found === null) {
                $left[] = $s;
            } else {
                $pairs[] = [$s, $found];
            }
        }

        $onlySheet = [];

        foreach ($left as $s) {
            // Lance sem atleta no sistema (resultado rápido, pedido de tempo): confere pela quantidade.
            $found = null;

            if ($anonymousMatchesAny) {
                [$found, $systemRows] = $this->take($systemRows, fn ($y) => $y['shirt'] === null && $y['name'] === null);
            }
            if ($found === null) {
                $onlySheet[] = $s;
            } else {
                $pairs[] = [$s, $found];
            }
        }

        return [$pairs, $onlySheet, $systemRows];
    }

    /**
     * Tira a primeira linha que atende ao teste: devolve ela (ou null) e as linhas que sobraram.
     *
     * @param  list<Row>  $rows
     * @return array{0: Row|null, 1: list<Row>}
     */
    private function take(array $rows, callable $test): array
    {
        foreach ($rows as $index => $row) {
            if ($test($row)) {
                array_splice($rows, $index, 1);

                return [$row, $rows];
            }
        }

        return [null, $rows];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function describe(array $row, bool $lineup): string
    {
        $who = trim(($row['name'] ?? '').($row['shirt'] !== null ? ' (nº '.$row['shirt'].')' : ''));
        $text = $who === '' ? 'sem atleta' : $who;

        if (($row['detail'] ?? null) !== null && ($row['type'] ?? null) === 'substitution') {
            $text .= ", saiu {$row['detail']}";
        }

        if ($lineup && is_bool($row['starter'] ?? null)) {
            $text .= $row['starter'] ? ' — titular' : ' — reserva';
        }

        if (! $lineup && ($row['minute'] ?? null) !== null) {
            $text .= " — {$row['minute']}'";
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function person(array $row): string
    {
        return $row['name'] ?? ($row['shirt'] !== null ? 'Camisa '.$row['shirt'] : 'Sem nome');
    }

    /**
     * @param  Facts  $facts
     */
    private function teamName(array $facts, string $side): string
    {
        return $facts[$side.'_team'] ?? ($side === 'home' ? 'Casa' : 'Visitante');
    }

    /**
     * @return Item
     */
    private function item(string $section, string $label, string $status, ?string $sheet, ?string $system, ?string $note): array
    {
        return ['section' => $section, 'label' => $label, 'status' => $status, 'sheet' => $sheet, 'system' => $system, 'note' => $note];
    }
}

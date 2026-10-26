<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Enums\OfficialRole;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameDocumentSignature;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Support\Pdf\PdfDocument;
use App\Support\Pdf\PdfMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Relatórios PDF internos da partida (escalação pré-partida, atividades e relatório final), desenhados com cartões,
 * cores e tabelas. Uso do organizador: nunca vai a snapshot, overlay ou página pública.
 */
final class GameReportPdf
{
    private const INK = '#111827';

    private const MUTED = '#6b7280';

    private const BORDER = '#e5e7eb';

    private const SOFT = '#f3f4f6';

    private const BRAND = '#047857';

    private const DARK = '#064e3b';

    public static function lineup(Game $game): string
    {
        self::load($game);
        $pdf = self::start($game, 'Escalação pré-partida');

        self::matchCard($pdf, $game, false);
        self::lineupCards($pdf, $game);
        self::signatures($pdf, ['Responsável — '.$game->homeEntry->team->name, 'Responsável — '.$game->awayEntry->team->name, 'Árbitro / mesário']);
        self::signatureEvidence($pdf, $game, 'lineup');

        return $pdf->output();
    }

    /**
     * Súmula oficial para imprimir ou arquivar: cabeçalho, arbitragem e mesa, escalações, ocorrências (linhas em branco
     * quando o jogo ainda não tem lançamentos, para preencher à mão), observações e assinaturas.
     */
    public static function officialSheet(Game $game): string
    {
        self::load($game);
        $pdf = self::start($game, 'Súmula oficial do jogo');
        $played = in_array($game->status, [GameStatus::Finished, GameStatus::Walkover, GameStatus::InProgress], true);

        self::matchCard($pdf, $game, $played);
        self::lineupCards($pdf, $game);
        self::officialsTable($pdf, $game);

        $events = $game->events()->effective()->with(['entry.team', 'person'])->orderBy('id')->get()
            ->reject(fn (GameEvent $event): bool => $event->type->isClockMark() || in_array($event->type, [GameEventType::ShotClockReset, GameEventType::PeriodStart, GameEventType::PeriodEnd], true))
            ->values();

        self::occurrences($pdf, $events);
        self::notes($pdf);

        $home = $game->homeEntry;
        $away = $game->awayEntry;
        self::signatures(
            $pdf,
            ['Árbitro', 'Capitão — '.$home->team->name, 'Capitão — '.$away->team->name, 'Mesário'],
            [self::officialName($game, OfficialRole::Referee), self::captain($game, $home), self::captain($game, $away), self::officialName($game, OfficialRole::Scorer)],
        );
        self::signatureEvidence($pdf, $game, 'sheet');

        return $pdf->output();
    }

    public static function finalReport(Game $game, string $type = 'full'): string
    {
        self::load($game);
        $type = in_array($type, ['lineup', 'activities', 'full'], true) ? $type : 'full';
        $pdf = self::start($game, match ($type) {
            'lineup' => 'Relatório de escalação',
            'activities' => 'Relatório de atividades da partida',
            default => 'Relatório final da partida',
        });

        self::matchCard($pdf, $game, $type !== 'lineup');

        if ($type === 'lineup') {
            self::lineupCards($pdf, $game);
            self::signatures($pdf, ['Responsável — '.$game->homeEntry->team->name, 'Responsável — '.$game->awayEntry->team->name, 'Árbitro / mesário']);
            self::signatureEvidence($pdf, $game, 'lineup');

            return $pdf->output();
        }

        $events = $game->events()->effective()->with(['entry.team', 'person', 'secondaryPerson'])->orderBy('id')->get();

        self::summary($pdf, $game, $events);

        if ($type === 'full') {
            self::lineupCards($pdf, $game);
        }

        self::timeline($pdf, $events);
        self::notes($pdf);
        self::signatures($pdf, ['Árbitro', 'Capitão — '.$game->homeEntry->team->name, 'Capitão — '.$game->awayEntry->team->name]);
        self::signatureEvidence($pdf, $game, 'final');

        return $pdf->output();
    }

    // ---------------------------------------------------------------- layout

    private static function start(Game $game, string $title): PdfDocument
    {
        $organization = CurrentOrganization::get()->name ?? config('app.name');
        $pdf = new PdfDocument($title.' — '.$game->homeEntry->team->name.' x '.$game->awayEntry->team->name, $organization.' · Documento interno, não divulgar · gerado em '.now()->format('d/m/Y H:i'));

        $pdf->rect(0, 0, PdfDocument::WIDTH, 112, self::DARK);
        $pdf->rect(0, 112, PdfDocument::WIDTH, 4, self::BRAND);
        $pdf->text(PdfDocument::MARGIN, 30, 'RELATÓRIO DA PARTIDA', 8.5, true, '#6ee7b7');
        $pdf->text(PdfDocument::MARGIN, 46, $title, 21, true, '#ffffff', 'left', PdfDocument::WIDTH - 2 * PdfDocument::MARGIN - 150);
        $pdf->text(PdfDocument::MARGIN, 78, $game->division->season->competition->name.' · '.$game->division->name, 10.5, false, '#d1fae5', 'left', 380);
        $pdf->text(PdfDocument::MARGIN, 93, $game->division->season->name.' · '.$game->stage->name.($game->round !== null ? ' · rodada '.$game->round : ''), 9, false, '#a7f3d0', 'left', 380);
        $pdf->text(PdfDocument::WIDTH - PdfDocument::MARGIN, 30, $organization, 9, true, '#d1fae5', 'right', 200);
        $pdf->moveTo(136);

        return $pdf;
    }

    private static function matchCard(PdfDocument $pdf, Game $game, bool $withScore): void
    {
        $x = PdfDocument::MARGIN;
        $w = $pdf->contentWidth();
        $y = $pdf->y();
        $height = 96;

        $pdf->rect($x, $y, $w, $height, '#ffffff', self::BORDER, 10);
        $sideWidth = ($w - 130) / 2;

        foreach ([[$game->homeEntry, $x + 16, 'Mandante', 'left'], [$game->awayEntry, $x + $w - 16, 'Visitante', 'right']] as [$entry, $edge, $role, $align]) {
            $team = $entry->team;
            $color = self::teamColor($team->primary_color);
            $cx = $align === 'left' ? $edge + 22 : $edge - 22;
            $pdf->circle($cx, $y + 40, 22, $color);
            $pdf->text($cx, $y + 33, self::initials($team->short_name ?: $team->name), 13, true, self::contrast($color), 'center');
            $textX = $align === 'left' ? $edge + 54 : $edge - 54;
            $pdf->text($textX, $y + 26, mb_strtoupper($role), 7.5, true, self::MUTED, $align);
            foreach (array_slice($pdf->wrap($team->name, $sideWidth - 60, 13, true), 0, 2) as $index => $line) {
                $pdf->text($textX, $y + 38 + $index * 16, $line, 13, true, self::INK, $align);
            }
        }

        $center = $x + $w / 2;

        if ($withScore) {
            $pdf->text($center, $y + 20, (string) $game->home_score.'  x  '.(string) $game->away_score, 30, true, self::INK, 'center');
        } else {
            $pdf->text($center, $y + 24, 'x', 26, true, self::MUTED, 'center');
        }

        $label = $game->status->label();
        $chipWidth = max(54.0, PdfMetrics::width($label, 8, true) + 18);
        $pdf->rect($center - $chipWidth / 2, $y + 66, $chipWidth, 16, $withScore ? self::BRAND : self::SOFT, null, 8);
        $pdf->text($center, $y + 70, $label, 8, true, $withScore ? '#ffffff' : self::MUTED, 'center');

        $pdf->moveTo($y + $height + 10);

        $fields = [
            ['Data e hora', $game->scheduled_at === null ? 'Não definida' : $game->scheduled_at->format('d/m/Y').' às '.$game->scheduled_at->format('H:i')],
            ['Local', $game->venue->name ?? 'Não definido'],
            ['Jogo', '#'.($game->number ?? '—').($game->round !== null ? ' · rodada '.$game->round : '')],
            ['Árbitro', $game->officials->firstWhere('role', OfficialRole::Referee)?->official->name ?? 'Não escalado'],
        ];

        $fieldWidth = $w / count($fields);
        $rowY = $pdf->y();
        foreach ($fields as $i => [$label, $value]) {
            $fx = $x + $i * $fieldWidth;
            $pdf->rect($fx + ($i === 0 ? 0 : 4), $rowY, $fieldWidth - ($i === 0 ? 4 : ($i === count($fields) - 1 ? 0 : 8)), 34, self::SOFT, null, 8);
            $pdf->text($fx + 12 + ($i === 0 ? 0 : 4), $rowY + 7, mb_strtoupper($label), 7, true, self::MUTED);
            $pdf->text($fx + 12 + ($i === 0 ? 0 : 4), $rowY + 18, $value, 10, true, self::INK, 'left', $fieldWidth - 28);
        }

        $pdf->moveTo($rowY + 34 + 14);

        if ($withScore && $game->sets !== null && $game->sets !== []) {
            $pdf->text($x, $pdf->y(), 'SETS', 7.5, true, self::MUTED);
            $cursor = $x + 30;
            foreach ($game->sets as $index => $set) {
                $pdf->rect($cursor, $pdf->y() - 3, 58, 17, self::SOFT, null, 6);
                $pdf->text($cursor + 29, $pdf->y(), ($index + 1).'º  '.$set['home'].'-'.$set['away'], 8.5, true, self::INK, 'center');
                $cursor += 64;
            }
            $pdf->skip(24);
        }
    }

    private static function section(PdfDocument $pdf, string $title, ?string $hint = null): void
    {
        $pdf->ensure(60);
        $pdf->skip(6);
        $pdf->rect(PdfDocument::MARGIN, $pdf->y() + 1, 4, 15, self::BRAND, null, 2);
        $pdf->text(PdfDocument::MARGIN + 12, $pdf->y(), $title, 13, true, self::INK);

        if ($hint !== null) {
            $pdf->text(PdfDocument::WIDTH - PdfDocument::MARGIN, $pdf->y() + 3, $hint, 8.5, false, self::MUTED, 'right', 260);
        }

        $pdf->skip(26);
    }

    /** @param Collection<int, GameEvent> $events */
    private static function summary(PdfDocument $pdf, Game $game, $events): void
    {
        self::section($pdf, 'Resumo da partida');

        $cards = [
            ['Gols', $events->whereIn('type', [GameEventType::Goal, GameEventType::OwnGoal])->count(), '#059669'],
            ['Cartões', $events->whereIn('type', [GameEventType::YellowCard, GameEventType::RedCard, GameEventType::BlueCard])->count(), '#d97706'],
            ['Substituições', $events->where('type', GameEventType::Substitution)->count(), '#2563eb'],
            ['Faltas', $events->whereIn('type', [GameEventType::Foul, GameEventType::TechnicalFoul, GameEventType::UnsportsmanlikeFoul])->count(), '#7c3aed'],
        ];

        $points = (int) $events->whereIn('type', GameEventType::scoring())->sum(fn (GameEvent $event): int => (int) (($event->payload ?? [])['points'] ?? $event->type->pointValue() ?? 0));
        if ($points > 0 && $events->whereIn('type', [GameEventType::FieldGoal, GameEventType::ThreePointer, GameEventType::FreeThrow])->isNotEmpty()) {
            $cards[] = ['Pontos', $points, '#0891b2'];
        }

        $gap = 8;
        $cardWidth = ($pdf->contentWidth() - $gap * (count($cards) - 1)) / count($cards);
        $y = $pdf->y();

        foreach ($cards as $i => [$label, $value, $color]) {
            $x = PdfDocument::MARGIN + $i * ($cardWidth + $gap);
            $pdf->rect($x, $y, $cardWidth, 58, '#ffffff', self::BORDER, 8);
            $pdf->rect($x + 12, $y, 28, 3.5, $color, null, 1.5);
            $pdf->text($x + 12, $y + 13, (string) $value, 24, true, $color);
            $pdf->text($x + 12, $y + 42, mb_strtoupper($label), 7.5, true, self::MUTED);
        }

        $pdf->moveTo($y + 58 + 14);

        $byPeriod = [];
        foreach ($events->whereIn('type', GameEventType::scoring()) as $event) {
            $side = $event->entry_id === $game->home_entry_id ? 'home' : ($event->entry_id === $game->away_entry_id ? 'away' : null);
            if ($side === null) {
                continue;
            }
            $period = (int) $event->period;
            $byPeriod[$period] ??= ['home' => 0, 'away' => 0];
            $byPeriod[$period][$side] += (int) (($event->payload ?? [])['points'] ?? $event->type->pointValue() ?? 0);
        }

        if ($byPeriod !== []) {
            ksort($byPeriod);
            $rows = [];
            foreach ($byPeriod as $period => $score) {
                $rows[] = [$period.'º período', (string) $score['home'], (string) $score['away']];
            }
            self::table($pdf, [['Placar por período', 200, 'left'], [$game->homeEntry->team->name, 150, 'center'], [$game->awayEntry->team->name, 0, 'center']], $rows);
        }
    }

    /** @param Collection<int, GameEvent> $events */
    private static function timeline(PdfDocument $pdf, $events): void
    {
        self::section($pdf, 'Linha do tempo', $events->count().' lance(s) válido(s)');

        if ($events->isEmpty()) {
            $pdf->rect(PdfDocument::MARGIN, $pdf->y(), $pdf->contentWidth(), 40, self::SOFT, null, 8);
            $pdf->text(PdfDocument::MARGIN + $pdf->contentWidth() / 2, $pdf->y() + 14, 'Nenhum evento registrado nesta partida.', 10, false, self::MUTED, 'center');
            $pdf->skip(52);

            return;
        }

        $rows = [];
        foreach ($events as $event) {
            $detail = '';
            if (is_array($event->payload) && isset($event->payload['kick'])) {
                $detail = (bool) ($event->payload['scored'] ?? false) ? 'convertida' : 'perdida';
            }
            $rows[] = [
                $event->period.'º',
                ['text' => $event->type->label(), 'chip' => self::eventColor($event->type)],
                $event->entry === null ? 'Partida' : $event->entry->team->name,
                trim(($event->person ? self::person($event->person) : '').($event->secondaryPerson ? ' / '.self::person($event->secondaryPerson) : '')) ?: '—',
                $detail,
            ];
        }

        self::table($pdf, [['Per.', 46, 'left'], ['Lance', 122, 'left'], ['Equipe', 150, 'left'], ['Atleta', 0, 'left'], ['Detalhe', 64, 'left']], $rows, 21);
    }

    /**
     * Tabela com cabeçalho que se repete a cada página e linhas alternadas. Coluna de largura 0 ocupa o que sobrar; célula
     * pode ser texto ou ['text' => ..., 'chip' => cor] (etiqueta colorida).
     *
     * @param  list<array{0: string, 1: int|float, 2: string}>  $columns
     * @param  list<list<string|array{text: string, chip: string}>>  $rows
     */
    private static function table(PdfDocument $pdf, array $columns, array $rows, float $rowHeight = 20, bool $ruled = false): void
    {
        $fixed = array_sum(array_map(fn (array $column): float => (float) $column[1], $columns));
        $flexible = count(array_filter($columns, fn (array $column): bool => $column[1] == 0));
        $widths = array_map(fn (array $column): float => $column[1] > 0 ? (float) $column[1] : ($pdf->contentWidth() - $fixed) / max(1, $flexible), $columns);

        $header = function () use ($pdf, $columns, $widths): void {
            $y = $pdf->y();
            $pdf->rect(PdfDocument::MARGIN, $y, $pdf->contentWidth(), 20, self::SOFT, null, 6);
            $x = PdfDocument::MARGIN;
            foreach ($columns as $i => [$label, , $align]) {
                $self = self::cellX($x, $widths[$i], $align);
                $pdf->text($self, $y + 6, mb_strtoupper($label), 7.5, true, self::MUTED, $align, $widths[$i] - 12);
                $x += $widths[$i];
            }
            $pdf->moveTo($y + 22);
        };

        $pdf->ensure(60);
        $header();

        foreach ($rows as $index => $row) {
            if ($pdf->ensure($rowHeight + 2)) {
                $header();
            }

            $y = $pdf->y();
            if ($index % 2 === 1) {
                $pdf->rect(PdfDocument::MARGIN, $y, $pdf->contentWidth(), $rowHeight, '#f9fafb', null, 4);
            }

            $x = PdfDocument::MARGIN;
            foreach ($row as $i => $cell) {
                $align = $columns[$i][2];
                $textX = self::cellX($x, $widths[$i], $align);
                if (is_array($cell)) {
                    $chipWidth = min($widths[$i] - 8, PdfMetrics::width($cell['text'], 8, true) + 14);
                    $pdf->rect($x + 6, $y + 3, $chipWidth, $rowHeight - 6, self::tint($cell['chip']), null, ($rowHeight - 6) / 2);
                    $pdf->text($x + 6 + 7, $y + ($rowHeight - 8) / 2 - 0.5, $cell['text'], 8, true, $cell['chip'], 'left', $chipWidth - 10);
                } else {
                    $pdf->text($textX, $y + ($rowHeight - 9) / 2 - 0.5, $cell, 9, $i === 0 && count($columns) === 3, self::INK, $align, $widths[$i] - 12);
                }
                $x += $widths[$i];
            }

            if ($ruled) {
                $pdf->line(PdfDocument::MARGIN, $y + $rowHeight, PdfDocument::WIDTH - PdfDocument::MARGIN, $y + $rowHeight, '#d1d5db', 0.5);
            }

            $pdf->moveTo($y + $rowHeight);
        }

        $pdf->skip(10);
    }

    private static function cellX(float $x, float $width, string $align): float
    {
        return match ($align) {
            'center' => $x + $width / 2,
            'right' => $x + $width - 8,
            default => $x + 8,
        };
    }

    private static function lineupCards(PdfDocument $pdf, Game $game): void
    {
        $gap = 12;
        $cardWidth = ($pdf->contentWidth() - $gap) / 2;
        $entries = [$game->homeEntry, $game->awayEntry];
        $blocks = array_map(fn ($entry): array => self::lineupBlock($game, $entry), $entries);
        $height = max(array_map(fn (array $block): float => self::blockHeight($block), $blocks));

        // O título só fica numa página onde os cartões também cabem (nada de título sozinho no pé da página).
        $pdf->ensure(min($height, 380) + 40);
        self::section($pdf, 'Escalações', 'Titulares, banco e comissão técnica');
        $y = $pdf->y();

        foreach ($entries as $i => $entry) {
            $x = PdfDocument::MARGIN + $i * ($cardWidth + $gap);
            $color = self::teamColor($entry->team->primary_color);
            $block = $blocks[$i];

            $pdf->rect($x, $y, $cardWidth, $height, '#ffffff', self::BORDER, 10);
            $pdf->rect($x, $y, $cardWidth, 34, $color, null, 10);
            $pdf->rect($x, $y + 20, $cardWidth, 14, $color);
            $pdf->text($x + 12, $y + 8, $entry->team->name, 11.5, true, self::contrast($color), 'left', $cardWidth - 100);
            $pdf->text($x + $cardWidth - 12, $y + 10, $block['formation'], 8.5, true, self::contrast($color), 'right', 90);

            $cursor = $y + 44;
            foreach ($block['groups'] as [$label, $people]) {
                $pdf->text($x + 12, $cursor, mb_strtoupper($label).' ('.count($people).')', 7.5, true, self::MUTED);
                $cursor += 14;

                if ($people === []) {
                    $pdf->text($x + 12, $cursor, '—', 9, false, self::MUTED);
                    $cursor += 15;
                }

                foreach ($people as [$number, $name, $captain]) {
                    $pdf->rect($x + 12, $cursor - 1.5, 22, 13, self::SOFT, null, 4);
                    $pdf->text($x + 23, $cursor, $number, 8, true, self::INK, 'center');
                    $pdf->text($x + 42, $cursor, $name.($captain ? '  (C)' : ''), 9, $captain, self::INK, 'left', $cardWidth - 56);
                    $cursor += 15;
                }

                $cursor += 6;
            }
        }

        $pdf->moveTo($y + $height + 14);
    }

    /**
     * @return array{formation: string, groups: list<array{0: string, 1: list<array{0: string, 1: string, 2: bool}>}>}
     */
    private static function lineupBlock(Game $game, $entry): array
    {
        /** @var GameTactic|null $tactic */
        $tactic = $game->tactics->firstWhere('entry_id', $entry->getKey());
        $rows = $game->lineups->where('entry_id', $entry->getKey());
        $numbers = RosterEntry::query()->where('entry_id', $entry->getKey())->whereIn('person_id', $rows->pluck('person_id'))->pluck('shirt_number', 'person_id');

        $groups = [];
        foreach ([[LineupSlot::Starter, 'Titulares'], [LineupSlot::Bench, 'Banco'], [LineupSlot::Staff, 'Comissão']] as [$slot, $label]) {
            $people = $rows->where('slot', $slot)->sortBy('position')->map(fn ($row): array => [
                (string) ($numbers[$row->person_id] ?? '—'),
                self::person($row->person),
                (bool) $row->is_captain,
            ])->values()->all();
            $groups[] = [$label, $people];
        }

        return [
            'formation' => $tactic === null ? 'Esquema não informado' : $tactic->formation.($tactic->isConfirmed() ? ' · confirmada' : ''),
            'groups' => $groups,
        ];
    }

    /** @param array{formation: string, groups: list<array{0: string, 1: list<mixed>}>} $block */
    private static function blockHeight(array $block): float
    {
        $height = 44.0;
        foreach ($block['groups'] as [, $people]) {
            $height += 14 + max(1, count($people)) * 15 + 6;
        }

        return $height + 4;
    }

    private static function notes(PdfDocument $pdf): void
    {
        self::section($pdf, 'Observações');
        $pdf->ensure(96);
        $y = $pdf->y();
        $pdf->rect(PdfDocument::MARGIN, $y, $pdf->contentWidth(), 80, '#ffffff', self::BORDER, 8);
        foreach ([1, 2, 3] as $i) {
            $pdf->line(PdfDocument::MARGIN + 12, $y + $i * 22 + 2, PdfDocument::WIDTH - PdfDocument::MARGIN - 12, $y + $i * 22 + 2, '#d1d5db', 0.6, true);
        }
        $pdf->moveTo($y + 96);
    }

    private static function signatureEvidence(PdfDocument $pdf, Game $game, string $documentType): void
    {
        $signatures = GameDocumentSignature::query()
            ->where('game_id', $game->getKey())
            ->where('document_type', $documentType)
            ->orderBy('created_at')
            ->get();

        if ($signatures->isEmpty()) {
            return;
        }

        self::section($pdf, 'Evidências de assinatura', 'Hash SHA-256 da versão enviada e confirmação registrada');
        $rows = $signatures->map(fn (GameDocumentSignature $signature): array => [
            $signature->signer_name,
            $signature->signer_role,
            $signature->status === 'signed' ? 'Confirmada' : 'Pendente',
            $signature->signed_at === null ? '—' : Carbon::parse((string) $signature->signed_at)->format('d/m/Y H:i').' UTC',
            substr((string) $signature->document_hash, 0, 16).'…',
        ])->all();

        self::table($pdf, [['Pessoa', 150, 'left'], ['Papel', 105, 'left'], ['Estado', 80, 'left'], ['Data', 105, 'left'], ['Hash', 0, 'left']], $rows, 20);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string|null>  $names  nome impresso acima do rótulo (quando já se sabe quem é)
     */
    private static function signatures(PdfDocument $pdf, array $labels, array $names = []): void
    {
        $pdf->ensure(80);
        $pdf->skip(30);
        $gap = 18;
        $width = ($pdf->contentWidth() - $gap * (count($labels) - 1)) / count($labels);
        $y = $pdf->y();

        foreach ($labels as $i => $label) {
            $x = PdfDocument::MARGIN + $i * ($width + $gap);
            $pdf->line($x, $y, $x + $width, $y, self::INK, 0.8);
            $pdf->text($x + $width / 2, $y + 5, $label, 8.5, false, self::MUTED, 'center', $width);

            if (($names[$i] ?? null) !== null && $names[$i] !== '') {
                $pdf->text($x + $width / 2, $y + 17, (string) $names[$i], 8.5, true, self::INK, 'center', $width);
            }
        }

        $pdf->moveTo($y + 36);
    }

    private static function officialsTable(PdfDocument $pdf, Game $game): void
    {
        $pdf->ensure(190); // tabela pequena: fica inteira numa página só
        self::section($pdf, 'Arbitragem e mesa');

        $rows = array_map(fn (OfficialRole $role): array => [$role->label(), self::officialName($game, $role) ?? ''], OfficialRole::cases());

        self::table($pdf, [['Função', 150, 'left'], ['Nome', 0, 'left']], $rows, 20, true);
    }

    /** @param Collection<int, GameEvent> $events */
    private static function occurrences(PdfDocument $pdf, $events): void
    {
        self::section($pdf, 'Ocorrências', 'Gols, cartões, substituições e demais lances');

        $rows = [];
        foreach ($events as $event) {
            $rows[] = [
                $event->minute !== null ? $event->minute."'" : '',
                $event->period.'º',
                ['text' => $event->type->label(), 'chip' => self::eventColor($event->type)],
                $event->entry === null ? 'Partida' : $event->entry->team->name,
                $event->person ? self::person($event->person) : '',
            ];
        }

        // Sem lançamentos: linhas em branco para preencher à mão.
        for ($i = count($rows); $i < 8; $i++) {
            $rows[] = ['', '', '', '', ''];
        }

        self::table($pdf, [['Min.', 44, 'left'], ['Per.', 40, 'left'], ['Ocorrência', 130, 'left'], ['Equipe', 150, 'left'], ['Atleta', 0, 'left']], $rows, 21, true);
    }

    private static function officialName(Game $game, OfficialRole $role): ?string
    {
        return $game->officials->firstWhere('role', $role)?->official->name;
    }

    private static function captain(Game $game, $entry): ?string
    {
        $row = $game->lineups->where('entry_id', $entry->getKey())->firstWhere('is_captain', true);

        return $row === null ? null : self::person($row->person);
    }

    // --------------------------------------------------------------- helpers

    private static function load(Game $game): void
    {
        $game->load(['homeEntry.team', 'awayEntry.team', 'division.season.competition', 'stage', 'venue', 'lineups.person', 'tactics', 'officials.official']);
    }

    private static function person(?Person $person): string
    {
        return $person === null ? 'Pessoa não identificada' : trim($person->nickname ?: $person->name);
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = count($words) > 1 ? mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1) : mb_substr($name, 0, 3);

        return mb_strtoupper($letters);
    }

    private static function teamColor(?string $color): string
    {
        return $color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : self::BRAND;
    }

    /** Preto ou branco, conforme a luminosidade do fundo. */
    private static function contrast(string $hex): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111827' : '#ffffff';
    }

    /** Versão clara da cor, para o fundo das etiquetas. */
    private static function tint(string $hex): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $mix = fn (int $c): string => str_pad(dechex((int) round($c + (255 - $c) * 0.86)), 2, '0', STR_PAD_LEFT);

        return '#'.$mix($r).$mix($g).$mix($b);
    }

    private static function eventColor(GameEventType $type): string
    {
        return match ($type) {
            GameEventType::Goal, GameEventType::Point, GameEventType::FreeThrow, GameEventType::FieldGoal, GameEventType::ThreePointer => '#047857',
            GameEventType::OwnGoal, GameEventType::RedCard, GameEventType::UnsportsmanlikeFoul => '#b91c1c',
            GameEventType::YellowCard, GameEventType::Warning, GameEventType::TechnicalFoul => '#b45309',
            GameEventType::BlueCard, GameEventType::Substitution, GameEventType::Timeout => '#1d4ed8',
            GameEventType::PenaltyKick => '#7c3aed',
            default => '#4b5563',
        };
    }
}

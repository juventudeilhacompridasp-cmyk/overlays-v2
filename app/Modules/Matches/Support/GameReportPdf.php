<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Teams\Models\Person;

/** Monta relatórios PDF de escalação e encerramento para uso interno do organizador. */
final class GameReportPdf
{
    /** @return list<string> */
    public static function lineup(Game $game): array
    {
        self::load($game);
        $lines = self::header($game, 'RELATÓRIO DE ESCALAÇÃO — PRÉ-PARTIDA');

        $lines = [...$lines, ...self::lineupLines($game)];

        $lines[] = '';
        $lines[] = 'Assinatura do responsável: ______________________________________________';

        return $lines;
    }

    /** @return list<string> */
    public static function finalReport(Game $game): array
    {
        self::load($game);
        $lines = self::header($game, 'RELATÓRIO FINAL DA PARTIDA');
        $lines[] = 'Placar final: '.$game->homeEntry->team->name.' '.$game->home_score.' x '.$game->away_score.' '.$game->awayEntry->team->name;
        if ($game->sets !== null) {
            $lines[] = 'Sets: '.implode(' | ', array_map(fn (array $set): string => $set['home'].'-'.$set['away'], $game->sets));
        }
        $lines[] = 'Situação: '.$game->status->label();
        $lines[] = '';
        $lines[] = 'ESCALAÇÕES REGISTRADAS';
        $lines = [...$lines, ...self::lineupLines($game, false)];
        $lines[] = '';
        $lines[] = 'LINHA DO TEMPO';
        foreach ($game->events()->effective()->with(['entry.team', 'person'])->orderBy('id')->get() as $event) {
            $team = $event->entry === null ? 'Partida' : $event->entry->team->name;
            $person = $event->person ? ' — '.self::person($event->person) : '';
            $detail = '';
            if (is_array($event->payload) && isset($event->payload['kick'])) {
                $detail = ' — cobrança '.((bool) ($event->payload['scored'] ?? false) ? 'convertida' : 'perdida');
            }
            $lines[] = 'Set/período '.$event->period.' · '.$event->type->label().' · '.$team.$person.$detail;
        }
        if ($game->events()->effective()->count() === 0) {
            $lines[] = 'Nenhum evento registrado.';
        }
        $lines[] = '';
        $lines[] = 'Observações: ___________________________________________________________';
        $lines[] = '________________________________________________________________________';

        return $lines;
    }

    private static function load(Game $game): void
    {
        $game->load(['homeEntry.team', 'awayEntry.team', 'division.season.competition', 'stage', 'lineups.person', 'tactics']);
    }

    /** @return list<string> */
    private static function lineupLines(Game $game, bool $withHeading = true): array
    {
        $lines = [];
        foreach ([$game->homeEntry, $game->awayEntry] as $entry) {
            $lines[] = '';
            $lines[] = 'EQUIPE: '.$entry->team->name;
            /** @var GameTactic|null $tactic */
            $tactic = $game->tactics->firstWhere('entry_id', $entry->getKey());
            $formation = $tactic === null ? 'Não informado' : $tactic->formation;
            $lines[] = 'Esquema: '.$formation.($tactic?->isConfirmed() === true ? ' (confirmado)' : '');
            foreach ([LineupSlot::Starter->value => 'Titulares', LineupSlot::Bench->value => 'Banco', LineupSlot::Staff->value => 'Comissão'] as $slot => $label) {
                $people = $game->lineups->where('entry_id', $entry->getKey())->where('slot', $slot)->sortBy('position');
                $lines[] = $label.': '.($people->isEmpty() ? '—' : $people->map(fn ($row): string => self::person($row->person))->implode(', '));
            }
        }

        return $withHeading ? $lines : array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /** @return list<string> */
    private static function header(Game $game, string $title): array
    {
        return [$title, $game->division->season->competition->name.' · '.$game->division->name, 'Fase: '.$game->stage->name, 'Jogo: '.$game->homeEntry->team->name.' x '.$game->awayEntry->team->name, 'Data: '.($game->scheduled_at?->format('d/m/Y H:i') ?? 'Não definida')];
    }

    private static function person(?Person $person): string
    {
        return $person === null ? 'Pessoa não identificada' : trim(($person->nickname ?: $person->name));
    }
}

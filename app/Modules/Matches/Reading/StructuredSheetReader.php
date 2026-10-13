<?php

namespace App\Modules\Matches\Reading;

use Illuminate\Support\Str;

/**
 * Leitor de súmula digital (CSV/XLSX) com cabeçalhos conhecidos — sem IA e sem serviço externo.
 *
 * Colunas: tipo, equipe, nome, camisa, minuto, valor (modelo em `TEMPLATE`). `tipo` = time, placar, escalacao, gol, gol_contra,
 * amarelo, vermelho, substituicao, falta, tempo, ponto ou observacao; `equipe` = casa ou fora.
 */
final class StructuredSheetReader implements SheetReader
{
    public const TEMPLATE = <<<'CSV'
tipo;equipe;nome;camisa;minuto;valor
time;casa;Nome da equipe da casa;;;
time;fora;Nome da equipe visitante;;;
placar;casa;;;;2
placar;fora;;;;1
escalacao;casa;Fulano de Tal;10;;titular
escalacao;casa;Beltrano;12;;reserva
gol;casa;Fulano de Tal;10;23;
amarelo;fora;Cicrano;5;40;
substituicao;casa;Beltrano;12;60;Fulano de Tal
observacao;;;;;Texto livre
CSV;

    private const TYPES = [
        'time' => 'team',
        'placar' => 'score',
        'escalacao' => 'lineup',
        'gol' => 'goal',
        'gol_contra' => 'own_goal',
        'amarelo' => 'yellow_card',
        'cartao_amarelo' => 'yellow_card',
        'vermelho' => 'red_card',
        'cartao_vermelho' => 'red_card',
        'substituicao' => 'substitution',
        'falta' => 'foul',
        'tempo' => 'timeout',
        'ponto' => 'point',
        'observacao' => 'notes',
    ];

    public function name(): string
    {
        return 'structured';
    }

    public function read(string $contents, string $mime, array $context = []): ExtractedSheet
    {
        $rows = str_contains($mime, 'spreadsheetml') ? SpreadsheetRows::fromXlsx($contents) : SpreadsheetRows::fromCsv($contents);

        if ($rows === []) {
            throw new SheetReadFailed('A planilha está vazia.');
        }

        $header = array_map(fn (string $cell) => Str::of(Str::ascii($cell))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString(), array_shift($rows));
        $columns = array_flip($header);

        if (! isset($columns['tipo'])) {
            throw new SheetReadFailed('Cabeçalho não reconhecido. Use o modelo da planilha (colunas: tipo, equipe, nome, camisa, minuto, valor).');
        }

        $data = ['home_team' => null, 'away_team' => null, 'home_score' => null, 'away_score' => null, 'lineups' => [], 'events' => [], 'notes' => null, 'confidence' => 1.0];

        foreach ($rows as $row) {
            $cell = fn (string $column): string => isset($columns[$column]) ? trim($row[$columns[$column]] ?? '') : '';
            $type = self::TYPES[Str::of(Str::ascii($cell('tipo')))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString()] ?? null;
            $team = $this->side($cell('equipe'));

            if ($type === null) {
                continue;
            }

            if ($type === 'notes') {
                $data['notes'] = trim(($data['notes'] ?? '').' '.$cell('valor'));

                continue;
            }

            if ($team === null) {
                continue;
            }

            match ($type) {
                'team' => $data[$team.'_team'] = $cell('nome'),
                'score' => $data[$team.'_score'] = $cell('valor'),
                'lineup' => $data['lineups'][] = [
                    'team' => $team,
                    'name' => $cell('nome'),
                    'shirt' => $cell('camisa'),
                    'starter' => match (Str::lower(Str::ascii($cell('valor')))) {
                        'titular' => true,
                        'reserva' => false,
                        default => null,
                    },
                    'confidence' => 1.0,
                ],
                default => $data['events'][] = [
                    'type' => $type,
                    'team' => $team,
                    'name' => $cell('nome'),
                    'shirt' => $cell('camisa'),
                    'minute' => $cell('minuto'),
                    'detail' => $cell('valor'),
                    'confidence' => 1.0,
                ],
            };
        }

        return ExtractedSheet::fromArray($data);
    }

    private function side(string $value): ?string
    {
        return match (Str::lower(Str::ascii($value))) {
            'casa', 'mandante', 'home' => 'home',
            'fora', 'visitante', 'away' => 'away',
            default => null,
        };
    }
}

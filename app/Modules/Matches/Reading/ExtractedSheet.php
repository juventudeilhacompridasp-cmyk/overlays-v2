<?php

namespace App\Modules\Matches\Reading;

/**
 * O que foi lido de uma súmula, no esquema fixo da ADR 0004. Não existe campo para CPF, RG, endereço ou telefone, e todo
 * texto passa por `clean()`, que remove sequências que parecem documento. Campo ilegível é `null`, nunca palpite.
 *
 * @phpstan-type Lineup array{team: 'home'|'away', name: ?string, shirt: ?int, starter: ?bool, confidence: ?float}
 * @phpstan-type Event array{type: string, team: 'home'|'away', name: ?string, shirt: ?int, minute: ?string, detail: ?string, confidence: ?float}
 */
final class ExtractedSheet
{
    public const EVENT_TYPES = ['goal', 'own_goal', 'yellow_card', 'red_card', 'substitution', 'foul', 'timeout', 'point', 'other'];

    private const MAX_LINEUPS = 60;

    private const MAX_EVENTS = 250;

    /**
     * @param  list<Lineup>  $lineups
     * @param  list<Event>  $events
     */
    public function __construct(
        public readonly ?string $homeTeam,
        public readonly ?string $awayTeam,
        public readonly ?int $homeScore,
        public readonly ?int $awayScore,
        public readonly array $lineups,
        public readonly array $events,
        public readonly ?string $notes,
        public readonly ?float $confidence,
    ) {}

    /**
     * Constrói a partir de dados soltos (resposta da IA, planilha), descartando o que não faz parte do esquema.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $lineups = [];

        foreach (array_slice(array_values((array) ($data['lineups'] ?? [])), 0, self::MAX_LINEUPS) as $row) {
            $team = is_array($row) ? self::side($row['team'] ?? null) : null;

            if ($team === null) {
                continue;
            }

            $lineups[] = [
                'team' => $team,
                'name' => self::clean($row['name'] ?? null, 120),
                'shirt' => self::shirt($row['shirt'] ?? null),
                'starter' => is_bool($row['starter'] ?? null) ? $row['starter'] : null,
                'confidence' => self::confidence($row['confidence'] ?? null),
            ];
        }

        $events = [];

        foreach (array_slice(array_values((array) ($data['events'] ?? [])), 0, self::MAX_EVENTS) as $row) {
            $team = is_array($row) ? self::side($row['team'] ?? null) : null;

            if ($team === null) {
                continue;
            }

            $type = is_string($row['type'] ?? null) ? $row['type'] : 'other';

            $events[] = [
                'type' => in_array($type, self::EVENT_TYPES, true) ? $type : 'other',
                'team' => $team,
                'name' => self::clean($row['name'] ?? null, 120),
                'shirt' => self::shirt($row['shirt'] ?? null),
                'minute' => self::clean($row['minute'] ?? null, 8),
                'detail' => self::clean($row['detail'] ?? null, 120),
                'confidence' => self::confidence($row['confidence'] ?? null),
            ];
        }

        return new self(
            self::clean($data['home_team'] ?? null, 120),
            self::clean($data['away_team'] ?? null, 120),
            self::score($data['home_score'] ?? null),
            self::score($data['away_score'] ?? null),
            $lineups,
            $events,
            self::clean($data['notes'] ?? null, 1000),
            self::confidence($data['confidence'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'home_team' => $this->homeTeam,
            'away_team' => $this->awayTeam,
            'home_score' => $this->homeScore,
            'away_score' => $this->awayScore,
            'lineups' => $this->lineups,
            'events' => $this->events,
            'notes' => $this->notes,
            'confidence' => $this->confidence,
        ];
    }

    /** Texto limpo: sem controle/HTML, sem sequências que parecem CPF/RG/telefone, com tamanho máximo. */
    public static function clean(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
        // Qualquer coisa com 9 ou mais dígitos (mesmo com pontos, traços ou espaços) é documento ou telefone: não guardamos.
        $text = (string) preg_replace('/\(?\d(?:[\s.\-\/()]*\d){8,}\)?/u', '[removido]', $text);
        $text = trim($text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private static function side(mixed $value): ?string
    {
        return in_array($value, ['home', 'away'], true) ? $value : null;
    }

    private static function shirt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 && (int) $value <= 999 ? (int) $value : null;
    }

    private static function score(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 && (int) $value <= 999 ? (int) $value : null;
    }

    private static function confidence(mixed $value): ?float
    {
        return is_numeric($value) ? round(max(0.0, min(1.0, (float) $value)), 2) : null;
    }
}

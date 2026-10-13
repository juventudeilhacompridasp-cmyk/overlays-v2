<?php

use App\Modules\Matches\Reading\NameMatcher;
use App\Modules\Matches\Reading\SheetComparison;

function cmpFacts(array $override = []): array
{
    return array_merge([
        'home_team' => 'Leões',
        'away_team' => 'Águias',
        'home_score' => 2,
        'away_score' => 1,
        'lineups' => [],
        'events' => [],
    ], $override);
}

function cmpSheet(array $override = []): array
{
    return array_merge([
        'home_team' => 'Leões',
        'away_team' => 'Águias',
        'home_score' => 2,
        'away_score' => 1,
        'lineups' => [],
        'events' => [],
        'notes' => null,
    ], $override);
}

function cmpRow(string $team, ?string $name, ?int $shirt, array $extra = []): array
{
    return array_merge(['team' => $team, 'name' => $name, 'shirt' => $shirt, 'starter' => null, 'minute' => null, 'detail' => null], $extra);
}

function cmpStatuses(array $items, ?string $sectionPrefix = null): array
{
    return array_values(array_map(fn ($item) => $item['status'], array_filter($items, fn ($item) => $sectionPrefix === null || str_starts_with($item['section'], $sectionPrefix))));
}

describe('names', function () {
    it('ignores accents, case and abbreviations', function () {
        expect(NameMatcher::same('JOÃO da Silva', 'joao da silva'))->toBeTrue()
            ->and(NameMatcher::same('Fulano', 'Fulano de Tal'))->toBeTrue()
            ->and(NameMatcher::same('Marcos Vinicius', 'Marcos Vinícius'))->toBeTrue()
            ->and(NameMatcher::same('Fulano', 'Beltrano'))->toBeFalse()
            ->and(NameMatcher::same(null, 'Fulano'))->toBeFalse();
    });
});

describe('score and teams', function () {
    it('matches equal data and flags differences and missing data', function () {
        $items = (new SheetComparison)->compare(cmpSheet(['home_score' => 3, 'away_score' => null, 'away_team' => 'Outro']), cmpFacts());

        expect(cmpStatuses($items, 'Placar'))->toBe(['differs', 'unclear'])
            ->and(cmpStatuses($items, 'Equipes'))->toBe(['match', 'differs'])
            ->and($items[0]['sheet'])->toBe('3')
            ->and($items[0]['system'])->toBe('2');
    });
});

describe('lineups', function () {
    it('pairs by shirt, then by name, and never guesses', function () {
        $facts = cmpFacts(['lineups' => [
            cmpRow('home', 'Fulano de Tal', 10, ['starter' => true]),
            cmpRow('home', 'Beltrano Souza', 12, ['starter' => false]),
            cmpRow('home', 'Cicrano', 7, ['starter' => true]),
        ]]);
        $sheet = cmpSheet(['lineups' => [
            cmpRow('home', 'Fulano', 10, ['starter' => true]),      // confere
            cmpRow('home', 'Beltrano Souza', 12, ['starter' => true]), // titular x reserva
            cmpRow('home', 'Desconhecido', 99, ['starter' => true]),   // só na súmula
        ]]);

        $items = array_values(array_filter((new SheetComparison)->compare($sheet, $facts), fn ($i) => str_starts_with($i['section'], 'Escalação')));

        expect(cmpStatuses($items))->toBe(['match', 'differs', 'only_sheet', 'only_system'])
            ->and($items[1]['note'])->toContain('× reserva')
            ->and($items[3]['label'])->toBe('Cicrano');
    });

    it('flags the same shirt with a different name', function () {
        $items = (new SheetComparison)->compare(
            cmpSheet(['lineups' => [cmpRow('away', 'Zé', 9)]]),
            cmpFacts(['lineups' => [cmpRow('away', 'Cicrano', 9)]]),
        );

        $lineup = array_values(array_filter($items, fn ($i) => str_starts_with($i['section'], 'Escalação')));
        expect($lineup[0]['status'])->toBe('differs')->and($lineup[0]['note'])->toMatch('/nome diferente/i');
    });

    it('says so once when the system has no lineup', function () {
        $items = (new SheetComparison)->compare(cmpSheet(['lineups' => [cmpRow('home', 'A', 1), cmpRow('home', 'B', 2)]]), cmpFacts());

        $lineup = array_values(array_filter($items, fn ($i) => str_starts_with($i['section'], 'Escalação')));
        expect($lineup)->toHaveCount(1)
            ->and($lineup[0]['status'])->toBe('only_sheet')
            ->and($lineup[0]['sheet'])->toContain('2 atleta');
    });
});

describe('events', function () {
    it('counts goals per team and player', function () {
        $facts = cmpFacts(['events' => [
            ['type' => 'goal'] + cmpRow('home', 'Fulano', 10),
            ['type' => 'goal'] + cmpRow('home', 'Fulano', 10),
            ['type' => 'goal'] + cmpRow('away', 'Cicrano', 9),
        ]]);
        $sheet = cmpSheet(['events' => [
            ['type' => 'goal'] + cmpRow('home', 'Fulano', 10, ['minute' => '23']),
            ['type' => 'goal'] + cmpRow('away', 'Cicrano', 9),
            ['type' => 'goal'] + cmpRow('away', 'Outro', 4),
        ]]);

        $items = (new SheetComparison)->compare($sheet, $facts);

        expect(cmpStatuses($items, 'Gols — Leões'))->toBe(['match', 'only_system'])
            ->and(cmpStatuses($items, 'Gols — Águias'))->toBe(['match', 'only_sheet']);
    });

    it('lets an event without athlete in the system match by quantity', function () {
        $items = (new SheetComparison)->compare(
            cmpSheet(['events' => [['type' => 'goal'] + cmpRow('home', 'Fulano', 10)]]),
            cmpFacts(['events' => [['type' => 'goal'] + cmpRow('home', null, null)]]),
        );

        $goals = array_values(array_filter($items, fn ($i) => str_starts_with($i['section'], 'Gols — Leões')));
        expect($goals[0]['status'])->toBe('match')->and($goals[0]['note'])->toContain('só a quantidade');
    });

    it('compares who left in a substitution and pairs timeouts', function () {
        $sub = ['type' => 'substitution'];
        $items = (new SheetComparison)->compare(
            cmpSheet(['events' => [$sub + cmpRow('home', 'Beltrano', 12, ['detail' => 'Fulano']), ['type' => 'timeout'] + cmpRow('home', null, null)]]),
            cmpFacts(['events' => [$sub + cmpRow('home', 'Beltrano', 12, ['detail' => 'Cicrano']), ['type' => 'timeout'] + cmpRow('home', null, null)]]),
        );

        expect(cmpStatuses($items, 'Substituições — Leões'))->toBe(['differs'])
            ->and(cmpStatuses($items, 'Pedidos de tempo — Leões'))->toBe(['match']);
    });

    it('summarizes the statuses', function () {
        $items = (new SheetComparison)->compare(cmpSheet(['home_score' => 0]), cmpFacts());
        $summary = SheetComparison::summary($items);

        expect($summary)->toBe(['match' => 3, 'differs' => 1, 'only_sheet' => 0, 'only_system' => 0, 'unclear' => 0]);
    });
});

<?php

use App\Modules\Matches\Reading\ExtractedSheet;

it('removes anything that looks like a document or phone number from texts', function () {
    expect(ExtractedSheet::clean('João 123.456.789-09', 50))->toBe('João [removido]')
        ->and(ExtractedSheet::clean('RG 12.345.678-9', 50))->toBe('RG [removido]')
        ->and(ExtractedSheet::clean('Tel (11) 98888-7777', 50))->toBe('Tel [removido]')
        ->and(ExtractedSheet::clean('Camisa 10, minuto 45', 50))->toBe('Camisa 10, minuto 45')
        ->and(ExtractedSheet::clean('<b>Zé</b>   da   Silva', 50))->toBe('Zé da Silva')
        ->and(ExtractedSheet::clean('   ', 50))->toBeNull()
        ->and(ExtractedSheet::clean(['x'], 50))->toBeNull()
        ->and(ExtractedSheet::clean(str_repeat('a', 300), 20))->toHaveLength(20);
});

it('keeps only the schema and tolerates garbage', function () {
    $sheet = ExtractedSheet::fromArray([
        'home_score' => 'abc',
        'away_score' => -1,
        'lineups' => 'não é lista',
        'events' => [['type' => 'goal', 'team' => 'home', 'confidence' => 5], 'lixo', ['type' => 'goal', 'team' => 'x']],
        'cpf' => '123',
    ]);

    expect($sheet->homeScore)->toBeNull()
        ->and($sheet->awayScore)->toBeNull()
        ->and($sheet->lineups)->toBe([])
        ->and($sheet->events)->toHaveCount(1)
        ->and($sheet->events[0]['confidence'])->toBe(1.0)
        ->and($sheet->toArray())->not->toHaveKey('cpf');
});

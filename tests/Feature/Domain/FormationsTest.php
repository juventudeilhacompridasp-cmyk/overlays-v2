<?php

use App\Modules\Rules\Tactics\Formations;

it('offers formations for every supported sport', function () {
    foreach (['football', 'futsal', 'basketball', 'volleyball'] as $sport) {
        expect(Formations::for($sport))->not->toBeEmpty()
            ->and(Formations::default($sport)->code)->toBe(Formations::for($sport)[0]->code);
    }

    expect(fn () => Formations::for('curling'))->toThrow(InvalidArgumentException::class);
});

it('fills the right number of positions on the field', function () {
    $onField = fn (string $sport) => collect(Formations::for($sport))->map(fn ($formation) => collect($formation->slots)->reject->bench->count())->unique()->all();

    expect($onField('football'))->toBe([11])
        ->and($onField('futsal'))->toBe([5])
        ->and($onField('basketball'))->toBe([5])
        ->and($onField('volleyball'))->toBe([6]);
});

it('has unique slot keys and coordinates inside the drawing in every formation', function () {
    foreach (['football', 'futsal', 'basketball', 'volleyball'] as $sport) {
        foreach (Formations::for($sport) as $formation) {
            $keys = $formation->keys();

            expect($keys)->toBe(array_values(array_unique($keys)));

            foreach ($formation->slots as $slot) {
                expect($slot->x)->toBeBetween(0, 100)->and($slot->y)->toBeBetween(0, 100);
            }
        }
    }
});

it('places the football lines from the formation code', function () {
    $formation = Formations::find('football', '4-3-3');

    expect($formation->slot('gk')->abbr)->toBe('GOL')
        ->and(collect($formation->slots)->where('y', collect($formation->slots)->min('y'))->pluck('abbr')->all())->toBe(['PE', 'CA', 'PD'])
        ->and(Formations::find('football', '4-3-3x'))->toBeNull();
});

it('validates positions: existing slot, used once, bench slots only for the bench', function () {
    $football = Formations::find('football', '4-4-2');

    expect(Formations::validate($football, ['a' => 'gk', 'b' => 'd1']))->toBeNull()
        ->and(Formations::validate($football, ['a' => 'gk', 'b' => 'gk']))->toContain('já está ocupada')
        ->and(Formations::validate($football, ['a' => 'xx']))->toContain('inexistente');

    $volleyball = Formations::find('volleyball', '5-1');

    expect(Formations::validate($volleyball, ['a' => 'z1'], ['b' => 'lib']))->toBeNull()
        ->and(Formations::validate($volleyball, ['a' => 'lib']))->toContain('reserva')
        ->and(Formations::validate($volleyball, [], ['b' => 'z2']))->toContain('só de titulares');
});

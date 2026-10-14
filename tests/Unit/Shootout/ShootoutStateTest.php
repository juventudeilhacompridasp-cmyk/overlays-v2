<?php

use App\Modules\Rules\Shootout\ShootoutState;

/** Monta as cobranças em ordem: `H` = mandante, `A` = visitante; maiúscula converte, minúscula perde. */
function shootoutKicks(string $sequence): array
{
    return array_map(fn (string $c) => ['side' => strtolower($c) === 'h' ? 'home' : 'away', 'scored' => $c === strtoupper($c)], str_split($sequence));
}

it('starts open to either team and then alternates from whoever kicked first', function () {
    expect(ShootoutState::from([])['next'])->toBeNull()
        ->and(ShootoutState::from(shootoutKicks('H'))['next'])->toBe('away')
        ->and(ShootoutState::from(shootoutKicks('HA'))['next'])->toBe('home')
        ->and(ShootoutState::from(shootoutKicks('AH'))['next'])->toBe('away')
        ->and(ShootoutState::from(shootoutKicks('AHA'))['next'])->toBe('home')
        ->and(ShootoutState::from(shootoutKicks('H'))['first'])->toBe('home');
});

it('counts goals and keeps the misses in order', function () {
    $state = ShootoutState::from(shootoutKicks('HahAhA'));

    expect($state['goals'])->toBe(['home' => 1, 'away' => 2])
        ->and($state['home'])->toBe([true, false, false])
        ->and($state['away'])->toBe([false, true, true])
        ->and($state['decided'])->toBeFalse();
});

it('ends early when the other team cannot catch up with the kicks left', function () {
    // 3 a 0 depois de 3 cobranças de cada: faltam 2 ao visitante, que no máximo chega a 2.
    expect(ShootoutState::from(shootoutKicks('HaHaHa')))->toMatchArray(['decided' => true, 'winner' => 'home', 'next' => null])
        // 2 a 0 depois de 2 cada: o visitante ainda alcança.
        ->and(ShootoutState::from(shootoutKicks('HaHa'))['decided'])->toBeFalse()
        ->and(ShootoutState::from(shootoutKicks('HaHaHaHA'))['winner'])->toBe('home')
        ->and(ShootoutState::from(shootoutKicks('hAhAhAhA'))['winner'])->toBe('away');
});

it('goes to sudden death after five each and decides only when both kicked the same number of times', function () {
    $tied = ShootoutState::from(shootoutKicks('HAHAHAHAHA'));

    expect($tied['decided'])->toBeFalse()->and($tied['goals'])->toBe(['home' => 5, 'away' => 5])->and($tied['next'])->toBe('home')
        // Mandante converte a sexta e o visitante ainda tem a dele.
        ->and(ShootoutState::from(shootoutKicks('HAHAHAHAHAH')))->toMatchArray(['decided' => false, 'next' => 'away'])
        // Visitante perde a sexta: acabou.
        ->and(ShootoutState::from(shootoutKicks('HAHAHAHAHAHa'))['winner'])->toBe('home')
        // Visitante converte: segue empatado.
        ->and(ShootoutState::from(shootoutKicks('HAHAHAHAHAHA'))['decided'])->toBeFalse()
        // Na sétima rodada o mandante perde e o visitante converte.
        ->and(ShootoutState::from(shootoutKicks('HAHAHAHAHAHAhA'))['winner'])->toBe('away');
});

it('knows the limit of kicks per team', function () {
    expect(ShootoutState::MAX_PER_SIDE)->toBe(30)->and(ShootoutState::ROUNDS)->toBe(5);
});

<?php

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Sheet\GameSheetState;
use App\Modules\Rules\Sheet\SheetEvent;

function sheet(?RuleSet $rules = null): GameSheetState
{
    $state = new GameSheetState($rules ?? RuleSet::defaults());
    $state->setLineup('H', ['h1', 'h2', 'h3'], ['h4', 'h5', 'h6', 'h7'], ['coach']);
    $state->setLineup('A', ['a1', 'a2'], ['a3'], []);

    return $state;
}

function ev(GameEventType $type, string $team, ?string $person = null, ?string $in = null, int $period = 1, ?int $minute = null): SheetEvent
{
    return new SheetEvent($type, $team, $person, $in, $period, $minute);
}

it('accepts goals only from players on the field', function () {
    $state = sheet();

    expect($state->check(ev(GameEventType::Goal, 'H', 'h1')))->toBeNull()
        ->and($state->check(ev(GameEventType::OwnGoal, 'H', 'h2')))->toBeNull()
        ->and($state->check(ev(GameEventType::Goal, 'H', 'h4')))->toBe('Só quem está em campo pode marcar gol.')
        ->and($state->check(ev(GameEventType::Goal, 'H', 'coach')))->toBe('Só quem está em campo pode marcar gol.')
        ->and($state->check(ev(GameEventType::Goal, 'H', 'a1')))->toBe('Só quem está em campo pode marcar gol.')
        ->and($state->check(ev(GameEventType::Goal, 'H')))->toBe('Informe o atleta.');
});

it('requires a lineup for the team', function () {
    $state = new GameSheetState(RuleSet::defaults());

    expect($state->check(ev(GameEventType::Goal, 'H', 'h1')))->toBe('A equipe ainda não tem escalação neste jogo.');
});

it('allows cards for starters, bench and staff but not for strangers', function () {
    $state = sheet();

    foreach (['h1', 'h4', 'coach'] as $person) {
        expect($state->check(ev(GameEventType::YellowCard, 'H', $person)))->toBeNull();
    }

    expect($state->check(ev(GameEventType::YellowCard, 'H', 'zzz')))->toBe('Esta pessoa não está na escalação do jogo.');
});

it('sends a player off after a red card or a second yellow and blocks further events', function () {
    $state = sheet();

    expect($state->tryApply(ev(GameEventType::YellowCard, 'H', 'h1')))->toBeNull()
        ->and($state->onField('H'))->toContain('h1')
        ->and($state->tryApply(ev(GameEventType::YellowCard, 'H', 'h1')))->toBeNull()
        ->and($state->onField('H'))->not->toContain('h1')
        ->and($state->expelled('H'))->toBe(['h1'])
        ->and($state->check(ev(GameEventType::Goal, 'H', 'h1')))->toBe('O atleta foi expulso e não pode receber novos lançamentos.')
        ->and($state->check(ev(GameEventType::YellowCard, 'H', 'h1')))->toBe('Esta pessoa já foi expulsa e não pode receber novos lançamentos.');

    expect($state->tryApply(ev(GameEventType::RedCard, 'H', 'h2')))->toBeNull()
        ->and($state->onField('H'))->toBe(['h3']);
});

it('substitutes a player on the field for a bench player', function () {
    $state = sheet();

    expect($state->tryApply(ev(GameEventType::Substitution, 'H', 'h1', 'h4', 2, 60)))->toBeNull()
        ->and($state->onField('H'))->toBe(['h2', 'h3', 'h4'])
        ->and($state->bench('H'))->toBe(['h5', 'h6', 'h7'])
        ->and($state->substitutionsUsed('H'))->toBe(1)
        ->and($state->windowsUsed('H'))->toBe(1)
        ->and($state->check(ev(GameEventType::Goal, 'H', 'h4')))->toBeNull()
        ->and($state->check(ev(GameEventType::Goal, 'H', 'h1')))->toBe('Só quem está em campo pode marcar gol.');
});

it('rejects invalid substitutions', function (SheetEvent $event, string $message) {
    $state = sheet();
    $state->apply(ev(GameEventType::Substitution, 'H', 'h1', 'h4', 1, 30));

    expect($state->check($event))->toBe($message);
})->with([
    'sai quem está no banco' => [fn () => ev(GameEventType::Substitution, 'H', 'h5', 'h6'), 'Quem sai precisa estar em campo.'],
    'entra quem já está em campo' => [fn () => ev(GameEventType::Substitution, 'H', 'h2', 'h3'), 'Quem entra precisa estar entre os reservas da escalação.'],
    'volta quem saiu' => [fn () => ev(GameEventType::Substitution, 'H', 'h2', 'h1'), 'Quem já jogou e saiu não pode voltar ao jogo.'],
    'mesma pessoa' => [fn () => ev(GameEventType::Substitution, 'H', 'h2', 'h2'), 'Quem sai e quem entra precisam ser pessoas diferentes.'],
    'sem reserva informado' => [fn () => ev(GameEventType::Substitution, 'H', 'h2'), 'Informe quem sai e quem entra.'],
]);

it('enforces the maximum number of substitutions', function () {
    $state = sheet(RuleSet::fromArray(['substitutions' => ['max' => 2, 'max_windows' => 5]]));

    expect($state->tryApply(ev(GameEventType::Substitution, 'H', 'h1', 'h4', 1, 20)))->toBeNull()
        ->and($state->tryApply(ev(GameEventType::Substitution, 'H', 'h2', 'h5', 1, 25)))->toBeNull()
        ->and($state->check(ev(GameEventType::Substitution, 'H', 'h3', 'h6', 2, 50)))->toBe('Limite de 2 substituições por jogo atingido.');
});

it('counts substitution windows as distinct moments, not substitutions', function () {
    $state = sheet(RuleSet::fromArray(['substitutions' => ['max' => 8, 'max_windows' => 2]]));

    // Duas trocas no mesmo momento = 1 ato.
    expect($state->tryApply(ev(GameEventType::Substitution, 'H', 'h1', 'h4', 2, 60)))->toBeNull()
        ->and($state->tryApply(ev(GameEventType::Substitution, 'H', 'h2', 'h5', 2, 60)))->toBeNull()
        ->and($state->windowsUsed('H'))->toBe(1)
        ->and($state->tryApply(ev(GameEventType::Substitution, 'H', 'h3', 'h6', 2, 70)))->toBeNull()
        ->and($state->windowsUsed('H'))->toBe(2)
        // Terceiro momento distinto: recusado. Mesmo momento já usado: aceito.
        ->and($state->check(ev(GameEventType::Substitution, 'H', 'h4', 'h7', 2, 80)))->toBe('Limite de 2 atos de substituição atingido.')
        ->and($state->check(ev(GameEventType::Substitution, 'H', 'h4', 'h7', 2, 70)))->toBeNull();
});

it('keeps the state of each team independent', function () {
    $state = sheet();

    $state->apply(ev(GameEventType::RedCard, 'H', 'h1'));

    expect($state->onField('A'))->toBe(['a1', 'a2'])
        ->and($state->expelled('A'))->toBe([])
        ->and($state->check(ev(GameEventType::Goal, 'A', 'a1')))->toBeNull();
});

it('does not change the state when an invalid event is attempted', function () {
    $state = sheet();

    expect($state->tryApply(ev(GameEventType::Substitution, 'H', 'h4', 'h1')))->not->toBeNull()
        ->and($state->onField('H'))->toBe(['h1', 'h2', 'h3'])
        ->and($state->substitutionsUsed('H'))->toBe(0);
});

it('lets a substituted-in player receive a card and be sent off', function () {
    $state = sheet();
    $state->apply(ev(GameEventType::Substitution, 'H', 'h1', 'h4', 1, 30));

    expect($state->tryApply(ev(GameEventType::RedCard, 'H', 'h4')))->toBeNull()
        ->and($state->onField('H'))->toBe(['h2', 'h3'])
        // Quem saiu (h1) ainda pode receber cartão de comissão técnica? Não: saiu e não está mais em campo, mas consta como usado na escalação.
        ->and($state->check(ev(GameEventType::YellowCard, 'H', 'h1')))->toBeNull();
});

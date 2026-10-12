<?php

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Models\Person;

it('records game events in order with a substitution payload', function () {
    $game = Game::factory()->create();
    $out = Person::factory()->create(['organization_id' => $game->organization_id]);
    $in = Person::factory()->create(['organization_id' => $game->organization_id]);

    GameEvent::factory()->for($game)->create(['type' => GameEventType::Goal, 'minute' => 10]);
    $substitution = GameEvent::factory()->for($game)->create([
        'type' => GameEventType::Substitution,
        'minute' => 60,
        'person_id' => $out->id,
        'secondary_person_id' => $in->id,
    ]);

    expect($game->events()->count())->toBe(2)
        ->and($substitution->person->is($out))->toBeTrue()
        ->and($substitution->secondaryPerson->is($in))->toBeTrue()
        ->and($substitution->type)->toBe(GameEventType::Substitution);
});

it('does not allow updating a game event', function () {
    $event = GameEvent::factory()->create();

    $event->update(['minute' => 99]);
})->throws(LogicException::class);

it('does not allow deleting a game event', function () {
    $event = GameEvent::factory()->create();

    $event->delete();
})->throws(LogicException::class);

it('compensates a wrong event with a new one instead of editing it', function () {
    $wrong = GameEvent::factory()->create(['type' => GameEventType::Goal]);

    $compensation = GameEvent::factory()->create([
        'game_id' => $wrong->game_id,
        'organization_id' => $wrong->organization_id,
        'type' => GameEventType::Goal,
        'compensates_event_id' => $wrong->id,
        'payload' => ['reason' => 'lançamento errado'],
    ]);

    expect($compensation->compensates->is($wrong))->toBeTrue()
        ->and($compensation->payload)->toBe(['reason' => 'lançamento errado'])
        ->and($wrong->fresh()->minute)->toBe($wrong->minute);
});

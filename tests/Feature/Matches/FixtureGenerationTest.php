<?php

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\ClearStageFixtures;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Models\Person;

/**
 * Divisão com N equipes confirmadas (cabeça de chave 1..N) e uma fase do tipo informado.
 *
 * @return array{stage: Stage, entries: list<Entry>}
 */
function fixtureDivision(int $teams, StageType $type = StageType::League): array
{
    $division = Division::factory()->create();
    $stage = Stage::factory()->for($division)->create(['type' => $type]);

    $entries = [];
    foreach (range(1, $teams) as $seed) {
        $entries[] = Entry::factory()->for($division)->create(['seed' => $seed, 'status' => EntryStatus::Confirmed]);
    }

    return ['stage' => $stage, 'entries' => $entries];
}

it('generates a single round robin league with numbered rounds', function () {
    ['stage' => $stage, 'entries' => $entries] = fixtureDivision(4);

    $created = app(GenerateStageFixtures::class)->handle($stage);

    $games = Game::query()->where('stage_id', $stage->id)->orderBy('number')->get();

    expect($created)->toBe(6)
        ->and($games)->toHaveCount(6)
        ->and($games->pluck('number')->all())->toBe([1, 2, 3, 4, 5, 6])
        ->and($games->pluck('round')->unique()->values()->all())->toBe([1, 2, 3])
        ->and($games->every(fn (Game $g) => $g->division_id === $stage->division_id && $g->group_id === null))->toBeTrue()
        ->and($games->every(fn (Game $g) => $g->organization_id === $stage->organization_id))->toBeTrue();

    $pairs = $games->map(fn (Game $g) => collect([$g->home_entry_id, $g->away_entry_id])->sort()->implode('|'))->unique();
    expect($pairs)->toHaveCount(6)
        ->and($games->pluck('home_entry_id')->merge($games->pluck('away_entry_id'))->unique()->sort()->values()->all())
        ->toBe(collect($entries)->pluck('id')->sort()->values()->all());
});

it('generates home and away legs when asked', function () {
    ['stage' => $stage] = fixtureDivision(4);

    expect(app(GenerateStageFixtures::class)->handle($stage, doubleRound: true))->toBe(12);
});

it('ignores teams that are not confirmed and needs at least two', function () {
    ['stage' => $stage, 'entries' => $entries] = fixtureDivision(3);
    $entries[2]->update(['status' => EntryStatus::Withdrawn]);

    expect(app(GenerateStageFixtures::class)->handle($stage))->toBe(1);

    ['stage' => $lonely] = fixtureDivision(1);
    app(GenerateStageFixtures::class)->handle($lonely);
})->throws(InvalidArgumentException::class, 'pelo menos duas equipes');

it('refuses to generate twice', function () {
    ['stage' => $stage] = fixtureDivision(3);
    app(GenerateStageFixtures::class)->handle($stage);

    app(GenerateStageFixtures::class)->handle($stage);
})->throws(InvalidArgumentException::class, 'já tem jogos');

it('generates a group stage distributing the teams in a snake by seed', function () {
    ['stage' => $stage, 'entries' => $entries] = fixtureDivision(8, StageType::Groups);

    $created = app(GenerateStageFixtures::class)->handle($stage, groups: 2);

    $groups = Group::query()->where('stage_id', $stage->id)->orderBy('sequence')->get();
    $byName = $groups->mapWithKeys(fn (Group $g) => [$g->name => $g->entries()->pluck('seed')->sort()->values()->all()]);

    expect($created)->toBe(12)
        ->and($groups->pluck('name')->all())->toBe(['Grupo A', 'Grupo B'])
        ->and($byName['Grupo A'])->toBe([1, 4, 5, 8])
        ->and($byName['Grupo B'])->toBe([2, 3, 6, 7]);

    foreach ($groups as $group) {
        $members = $group->entries()->pluck('entries.id');
        $games = Game::query()->where('group_id', $group->id)->get();

        expect($games)->toHaveCount(6)
            ->and($games->every(fn (Game $g) => $members->contains($g->home_entry_id) && $members->contains($g->away_entry_id)))->toBeTrue();
    }

    expect(Game::query()->where('stage_id', $stage->id)->pluck('number')->sort()->values()->all())->toBe(range(1, 12));
});

it('validates the number of groups', function () {
    ['stage' => $stage] = fixtureDivision(5, StageType::Groups);

    app(GenerateStageFixtures::class)->handle($stage, groups: 3);
})->throws(InvalidArgumentException::class, 'pelo menos duas equipes');

it('does not generate knockout fixtures yet', function () {
    ['stage' => $stage] = fixtureDivision(4, StageType::Knockout);

    app(GenerateStageFixtures::class)->handle($stage);
})->throws(InvalidArgumentException::class, 'mata-mata');

it('clears the fixtures and the generated groups', function () {
    ['stage' => $stage] = fixtureDivision(4, StageType::Groups);
    app(GenerateStageFixtures::class)->handle($stage, groups: 2);

    $removed = app(ClearStageFixtures::class)->handle($stage);

    expect($removed)->toBe(2)
        ->and(Game::query()->where('stage_id', $stage->id)->count())->toBe(0)
        ->and(Group::query()->where('stage_id', $stage->id)->count())->toBe(0);

    expect(app(GenerateStageFixtures::class)->handle($stage, groups: 2))->toBe(2);
});

it('refuses to clear fixtures once a game has been played', function () {
    ['stage' => $stage] = fixtureDivision(3);
    app(GenerateStageFixtures::class)->handle($stage);
    app(RecordQuickResult::class)->handle(Game::query()->where('stage_id', $stage->id)->firstOrFail(), 1, 0);

    app(ClearStageFixtures::class)->handle($stage);
})->throws(InvalidArgumentException::class, 'jogos realizados');

it('refuses to clear fixtures when a game already has events', function () {
    ['stage' => $stage] = fixtureDivision(3);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();

    GameEvent::factory()->create([
        'game_id' => $game->id, 'organization_id' => $game->organization_id,
        'entry_id' => $game->home_entry_id, 'type' => GameEventType::YellowCard,
        'person_id' => Person::factory()->create(['organization_id' => $game->organization_id])->id,
    ]);

    app(ClearStageFixtures::class)->handle($stage);
})->throws(InvalidArgumentException::class);

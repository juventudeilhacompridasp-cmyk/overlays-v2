<?php

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Group;
use App\Modules\Matches\Actions\ClearStageFixtures;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\ImportStageFixtures;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\Venue;
use App\Modules\Teams\Models\Person;

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

describe('import of a ready-made table', function () {
    it('imports league games with dates and venues without a draw', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(3);
        $venue = Venue::factory()->create(['name' => 'Estádio Municipal']);
        [$a, $b, $c] = array_map(fn ($entry) => $entry->team->name, $entries);

        $text = "Rodada;Mandante;Visitante;Data;Hora;Local\n1;{$a};{$b};10/05/2026;16:00;estadio municipal\n2;{$c};{$a};11/05/2026;18:30h;\n3;{$b};{$c};;;";
        $created = app(ImportStageFixtures::class)->handle($stage, $text);

        $games = Game::query()->where('stage_id', $stage->id)->orderBy('number')->get();
        expect($created)->toBe(3)
            ->and($games->pluck('round')->all())->toBe([1, 2, 3])
            ->and($games[0]->home_entry_id)->toBe($entries[0]->id)
            ->and($games[0]->venue_id)->toBe($venue->id)
            ->and($games[0]->scheduled_at->format('Y-m-d H:i'))->toBe('2026-05-10 16:00')
            ->and($games[1]->scheduled_at->format('H:i'))->toBe('18:30')
            ->and($games[2]->scheduled_at)->toBeNull();
    });

    it('creates the groups named in the table', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(4, StageType::Groups);
        [$a, $b, $c, $d] = array_map(fn ($entry) => $entry->team->name, $entries);

        app(ImportStageFixtures::class)->handle($stage, "1;{$a};{$b};;;;Grupo A\n1;{$c};{$d};;;;Grupo B");

        $groups = Group::query()->where('stage_id', $stage->id)->orderBy('sequence')->get();
        expect($groups->pluck('name')->all())->toBe(['Grupo A', 'Grupo B'])
            ->and($groups[0]->entries)->toHaveCount(2)
            ->and(Game::query()->where('stage_id', $stage->id)->whereNotNull('group_id')->count())->toBe(2);
    });

    it('imports nothing when any line has a problem', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(2);
        $a = $entries[0]->team->name;

        expect(fn () => app(ImportStageFixtures::class)->handle($stage, "1;{$a};Time Fantasma;;;\n1;{$a};{$a};;;\nx;{$a};{$a}"))
            ->toThrow(InvalidArgumentException::class, 'Time Fantasma');

        expect(Game::query()->where('stage_id', $stage->id)->count())->toBe(0);
    });

    it('requires the group on group stages and refuses knockout and non-empty stages', function () {
        ['stage' => $groups, 'entries' => $entries] = fixtureDivision(2, StageType::Groups);
        [$a, $b] = array_map(fn ($entry) => $entry->team->name, $entries);
        $import = app(ImportStageFixtures::class);

        expect(fn () => $import->handle($groups, "1;{$a};{$b}"))->toThrow(InvalidArgumentException::class, 'informe o grupo');

        ['stage' => $knockout] = fixtureDivision(2, StageType::Knockout);
        expect(fn () => $import->handle($knockout, '1;a;b'))->toThrow(InvalidArgumentException::class, 'mata-mata');

        ['stage' => $league, 'entries' => $entries] = fixtureDivision(2);
        [$a, $b] = array_map(fn ($entry) => $entry->team->name, $entries);
        $import->handle($league, "1;{$a};{$b}");
        expect(fn () => $import->handle($league, "2;{$b};{$a}"))->toThrow(InvalidArgumentException::class, 'já tem jogos');
    });
});

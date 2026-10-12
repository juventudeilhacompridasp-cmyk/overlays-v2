<?php

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

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

/**
 * @return array{game: Game, home: Entry, away: Entry, hp: list<string>, ap: list<string>}
 */
function sheetGame(): array
{
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);

    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    $players = fn (Entry $entry): array => RosterEntry::factory()->count(14)->sequence(fn ($s) => ['shirt_number' => $s->index + 1])
        ->create(['entry_id' => $entry->id])->pluck('person_id')->all();

    $hp = $players($game->homeEntry);
    $ap = $players($game->awayEntry);

    app(SaveGameLineup::class)->handle($game, $game->homeEntry, array_slice($hp, 0, 11), array_slice($hp, 11));
    app(SaveGameLineup::class)->handle($game, $game->awayEntry, array_slice($ap, 0, 11), array_slice($ap, 11));

    return ['game' => $game->fresh(), 'home' => $game->homeEntry, 'away' => $game->awayEntry, 'hp' => $hp, 'ap' => $ap];
}

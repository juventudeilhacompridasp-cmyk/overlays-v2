<?php

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameOfficial;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Database\QueryException;

it('builds the competition hierarchy with consistent organization ids', function () {
    $competition = Competition::factory()->create();
    $season = Season::factory()->for($competition)->create(['status' => SeasonStatus::Active, 'rules' => ['points' => ['win' => 3]]]);
    $division = Division::factory()->for($season)->create();
    $stage = Stage::factory()->for($division)->create(['type' => StageType::Groups]);
    $group = Group::factory()->for($stage)->create(['name' => 'Grupo A']);

    expect($competition->seasons)->toHaveCount(1)
        ->and($season->fresh()->status)->toBe(SeasonStatus::Active)
        ->and($season->fresh()->rules)->toBe(['points' => ['win' => 3]])
        ->and($season->divisions)->toHaveCount(1)
        ->and($division->stages)->toHaveCount(1)
        ->and($stage->groups->first()->is($group))->toBeTrue()
        ->and($stage->type)->toBe(StageType::Groups);

    foreach ([$season, $division, $stage, $group] as $model) {
        expect($model->organization_id)->toBe($competition->organization_id);
    }
});

it('registers teams in divisions, groups and rosters', function () {
    $division = Division::factory()->create();
    $stage = Stage::factory()->for($division)->create(['type' => StageType::Groups]);
    $group = Group::factory()->for($stage)->create();
    $entry = Entry::factory()->for($division)->create(['status' => EntryStatus::Confirmed]);

    $group->entries()->attach($entry);
    RosterEntry::factory()->for($entry)->count(2)->create(['role' => RosterRole::Athlete]);
    RosterEntry::factory()->for($entry)->create(['role' => RosterRole::Coach, 'shirt_number' => null]);

    expect($group->entries)->toHaveCount(1)
        ->and($entry->groups)->toHaveCount(1)
        ->and($entry->rosterEntries)->toHaveCount(3)
        ->and($entry->rosterEntries->where('role', RosterRole::Athlete))->toHaveCount(2)
        ->and($entry->team->organization_id)->toBe($division->organization_id);
});

it('creates games between two entries of the same division', function () {
    $stage = Stage::factory()->create();
    $game = Game::factory()->for($stage)->create(['round' => 3]);
    GameOfficial::factory()->for($game)->create();

    $game->refresh();

    expect($game->status)->toBe(GameStatus::Scheduled)
        ->and($game->division_id)->toBe($stage->division_id)
        ->and($game->homeEntry->division_id)->toBe($stage->division_id)
        ->and($game->awayEntry->division_id)->toBe($stage->division_id)
        ->and($game->officials)->toHaveCount(1)
        ->and($game->organization_id)->toBe($stage->organization_id);
});

it('does not allow the same team twice in a division', function () {
    $entry = Entry::factory()->create();

    Entry::factory()->create([
        'division_id' => $entry->division_id,
        'organization_id' => $entry->organization_id,
        'team_id' => $entry->team_id,
    ]);
})->throws(QueryException::class);

it('does not allow two stages with the same sequence in a division', function () {
    $stage = Stage::factory()->create(['sequence' => 1]);

    Stage::factory()->create([
        'division_id' => $stage->division_id,
        'organization_id' => $stage->organization_id,
        'sequence' => 1,
    ]);
})->throws(QueryException::class);

<?php

use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function profileSwitch(bool $on): void
{
    CurrentOrganization::get()->update(['settings' => ['portal' => ['athlete_profile' => $on]]]);
}

/** @return array{person: Person, entry: Entry, game: Game} */
function athleteWithGame(array $person = []): array
{
    ['stage' => $stage, 'entries' => [$a, $b]] = fixtureDivision(2);
    $person = Person::factory()->create($person + ['name' => 'João da Silva', 'nickname' => 'Joãozinho', 'birth_date' => '1990-04-02', 'public_profile_at' => now()]);
    RosterEntry::factory()->create(['entry_id' => $a->id, 'person_id' => $person->id, 'shirt_number' => 9]);
    $game = Game::factory()->create(['stage_id' => $stage->id, 'division_id' => $stage->division_id, 'home_entry_id' => $a->id, 'away_entry_id' => $b->id, 'status' => GameStatus::Finished, 'home_score' => 2, 'away_score' => 0, 'scheduled_at' => now()->subDay()]);
    GameLineup::factory()->create(['game_id' => $game->id, 'entry_id' => $a->id, 'person_id' => $person->id, 'slot' => LineupSlot::Starter]);
    GameEvent::factory()->count(2)->create(['game_id' => $game->id, 'type' => GameEventType::Goal, 'entry_id' => $a->id, 'person_id' => $person->id, 'period' => 1, 'minute' => 10]);
    GameEvent::factory()->create(['game_id' => $game->id, 'type' => GameEventType::YellowCard, 'entry_id' => $a->id, 'person_id' => $person->id, 'period' => 1, 'minute' => 30]);
    publishSeason($game);

    return ['person' => $person, 'entry' => $a, 'game' => $game];
}

it('is off by default, even with the athlete consent', function () {
    ['person' => $person] = athleteWithGame();

    $this->get(route('portal.athlete', $person))->assertNotFound();
});

it('shows only the public name, teams, shirt, games and goals when everything is allowed', function () {
    profileSwitch(true);
    ['person' => $person] = athleteWithGame();

    $response = $this->get(route('portal.athlete', $person))->assertOk()
        ->assertHeader('X-Robots-Tag')
        ->assertInertia(fn ($page) => $page
            ->component('Portal/Athlete')
            ->where('athlete.name', 'Joãozinho')
            ->where('athlete.totals', ['games' => 1, 'goals' => 2])
            ->where('athlete.participations.0.shirt', 9)
            ->where('seo.noindex', true));

    $content = json_encode($response->viewData('page')['props']);
    expect($content)->not->toContain('1990')->not->toContain('birth')->not->toContain('document')->not->toContain('photo')->not->toContain('yellow')->not->toContain('card');
});

it('needs the consent: no consent or revoked consent is "not found"', function () {
    profileSwitch(true);
    ['person' => $person] = athleteWithGame(['public_profile_at' => null]);

    $this->get(route('portal.athlete', $person))->assertNotFound();

    $person->update(['public_profile_at' => now()]);
    $this->get(route('portal.athlete', $person))->assertOk();

    $person->update(['public_profile_at' => null]);
    $this->get(route('portal.athlete', $person))->assertNotFound();
});

it('never exists for minors or unknown age, even with the consent flag', function () {
    profileSwitch(true);
    ['person' => $minor] = athleteWithGame(['birth_date' => now()->subYears(16)->toDateString()]);
    ['person' => $unknown] = athleteWithGame(['birth_date' => null]);

    $this->get(route('portal.athlete', $minor))->assertNotFound();
    $this->get(route('portal.athlete', $unknown))->assertNotFound();
});

it('does not exist for a draft season', function () {
    profileSwitch(true);
    ['person' => $person, 'game' => $game] = athleteWithGame();
    Season::query()->whereKey($game->division->season_id)->update(['status' => SeasonStatus::Draft]);

    $this->get(route('portal.athlete', $person))->assertNotFound();
});

it('links the roster to the profile only for people who allowed it', function () {
    profileSwitch(true);
    CurrentOrganization::get()->update(['settings' => ['portal' => ['athlete_profile' => true, 'team_roster' => true]]]);
    ['person' => $person, 'entry' => $entry] = athleteWithGame();
    $other = Person::factory()->create(['name' => 'Sem Autorização', 'birth_date' => '1991-01-01']);
    RosterEntry::factory()->create(['entry_id' => $entry->id, 'person_id' => $other->id, 'shirt_number' => 10]);

    $this->get(route('portal.team', $entry->team_id))->assertInertia(fn ($page) => $page
        ->where('extras.roster', fn ($roster) => collect($roster)->firstWhere('shirt', 9)['profile_url'] === route('portal.athlete', $person)
            && collect($roster)->firstWhere('shirt', 10)['profile_url'] === null));
});

describe('the consent in the registry', function () {
    it('records the consent date once and revokes it when unchecked', function () {
        $person = Person::factory()->create(['birth_date' => '1990-01-01', 'public_profile_at' => null]);
        $admin = panelUser(Role::Organizer);
        $payload = fn (bool $on) => ['name' => $person->name, 'birth_date' => '1990-01-01', 'public_profile' => $on];

        $this->actingAs($admin)->put(route('panel.people.update', $person), $payload(true))->assertSessionHasNoErrors();
        $first = $person->fresh()->public_profile_at;
        expect($first)->not->toBeNull();

        $this->actingAs($admin)->put(route('panel.people.update', $person), $payload(true))->assertSessionHasNoErrors();
        expect($person->fresh()->public_profile_at->equalTo($first))->toBeTrue();

        $this->actingAs($admin)->put(route('panel.people.update', $person), $payload(false))->assertSessionHasNoErrors();
        expect($person->fresh()->public_profile_at)->toBeNull();
    });

    it('refuses the consent for minors and without a birth date', function () {
        $person = Person::factory()->create(['birth_date' => '1990-01-01']);
        $admin = panelUser(Role::Organizer);

        $this->actingAs($admin)->put(route('panel.people.update', $person), ['name' => $person->name, 'birth_date' => now()->subYears(15)->toDateString(), 'public_profile' => true])->assertSessionHasErrors('public_profile');
        $this->actingAs($admin)->put(route('panel.people.update', $person), ['name' => $person->name, 'birth_date' => '', 'public_profile' => true])->assertSessionHasErrors('public_profile');
        expect($person->fresh()->public_profile_at)->toBeNull();
    });

    it('keeps the consent untouched when the form does not send the field', function () {
        $person = Person::factory()->create(['birth_date' => '1990-01-01', 'public_profile_at' => now()]);

        $this->actingAs(panelUser(Role::Organizer))->put(route('panel.people.update', $person), ['name' => 'Novo Nome', 'birth_date' => '1990-01-01'])->assertSessionHasNoErrors();
        expect($person->fresh()->public_profile_at)->not->toBeNull();
    });
});

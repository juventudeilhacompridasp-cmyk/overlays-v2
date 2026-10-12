<?php

use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));

    $this->actingAs(panelUser());
});

function addToRoster(Entry $entry, ?Person $person = null, string $role = 'athlete', ?int $shirt = null): RosterEntry
{
    return RosterEntry::factory()->create([
        'entry_id' => $entry->id,
        'organization_id' => $entry->organization_id,
        'person_id' => ($person ?? Person::factory()->create(['organization_id' => $entry->organization_id]))->id,
        'role' => $role,
        'shirt_number' => $shirt,
    ]);
}

it('shows the roster with athletes first, ordered by shirt, and staff after', function () {
    $entry = Entry::factory()->create();
    addToRoster($entry, role: 'coach');
    addToRoster($entry, shirt: 10);
    addToRoster($entry, shirt: 1);

    $this->get("/painel/entries/{$entry->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Panel/Entries/Show')
            ->has('roster', 3)
            ->where('roster.0.shirt_number', 1)
            ->where('roster.1.shirt_number', 10)
            ->where('roster.2.role', 'coach')
            ->where('roster.2.role_label', 'Técnico')
            ->has('options.roles', 5));
});

it('adds an existing person to the roster as an athlete with a shirt number', function () {
    $entry = Entry::factory()->create();
    $person = Person::factory()->create(['organization_id' => $entry->organization_id]);

    $this->post("/painel/entries/{$entry->id}/roster", ['person_id' => $person->id, 'role' => 'athlete', 'shirt_number' => 9])
        ->assertSessionHas('success');

    $item = RosterEntry::query()->firstOrFail();
    expect($item->person_id)->toBe($person->id)
        ->and($item->shirt_number)->toBe(9)
        ->and($item->status)->toBe(RosterStatus::Active)
        ->and($item->organization_id)->toBe($entry->organization_id);
});

it('creates a new person and adds them in one step, storing only the CPF hash', function () {
    $entry = Entry::factory()->create();

    $this->post("/painel/entries/{$entry->id}/roster", [
        'name' => 'Rafael Souza', 'nickname' => 'Rafa', 'birth_date' => '2003-02-20', 'document' => '529.982.247-25',
        'role' => 'athlete', 'shirt_number' => 7,
    ])->assertSessionHasNoErrors();

    $person = Person::query()->where('name', 'Rafael Souza')->firstOrFail();
    expect($person->document_hash)->not->toBeNull()
        ->and($person->organization_id)->toBe($entry->organization_id)
        ->and(RosterEntry::query()->where('person_id', $person->id)->count())->toBe(1);
});

it('does not create the new person when a roster rule fails', function () {
    $entry = Entry::factory()->create();
    addToRoster($entry, shirt: 10);

    $this->post("/painel/entries/{$entry->id}/roster", ['name' => 'Novo Jogador', 'role' => 'athlete', 'shirt_number' => 10])
        ->assertSessionHasErrors(['shirt_number' => 'Já existe um atleta com a camisa 10 neste elenco.']);

    expect(Person::query()->where('name', 'Novo Jogador')->exists())->toBeFalse();
});

it('requires a person or a name, a valid role and a valid shirt number', function () {
    $entry = Entry::factory()->create();

    $this->post("/painel/entries/{$entry->id}/roster", ['role' => 'athlete'])->assertSessionHasErrors('name');
    $this->post("/painel/entries/{$entry->id}/roster", ['name' => 'X', 'role' => 'goleiro'])->assertSessionHasErrors('role');
    $this->post("/painel/entries/{$entry->id}/roster", ['name' => 'X', 'role' => 'athlete', 'shirt_number' => 100])->assertSessionHasErrors('shirt_number');
    $this->post("/painel/entries/{$entry->id}/roster", ['name' => 'X', 'role' => 'athlete', 'document' => '123.456.789-00'])->assertSessionHasErrors('document');

    expect(RosterEntry::query()->count())->toBe(0);
});

it('lets a removed athlete free the shirt number and ignores the shirt for staff', function () {
    $entry = Entry::factory()->create();
    $old = addToRoster($entry, shirt: 5);
    $old->update(['status' => RosterStatus::Removed]);

    $this->post("/painel/entries/{$entry->id}/roster", ['name' => 'Substituto', 'role' => 'athlete', 'shirt_number' => 5])->assertSessionHasNoErrors();

    $this->post("/painel/entries/{$entry->id}/roster", ['name' => 'Professor', 'role' => 'coach', 'shirt_number' => 5])->assertSessionHasNoErrors();
    expect(RosterEntry::query()->where('role', RosterRole::Coach)->first()->shirt_number)->toBeNull();
});

it('does not allow the same function twice but allows athlete and coach for the same person', function () {
    $entry = Entry::factory()->create();
    $person = Person::factory()->create(['organization_id' => $entry->organization_id]);
    addToRoster($entry, $person, 'athlete', 8);

    $this->post("/painel/entries/{$entry->id}/roster", ['person_id' => $person->id, 'role' => 'athlete', 'shirt_number' => 9])
        ->assertSessionHasErrors('role');

    $this->post("/painel/entries/{$entry->id}/roster", ['person_id' => $person->id, 'role' => 'assistant_coach'])
        ->assertSessionHasNoErrors();
});

it('allows an athlete in only one team per division, but staff in many and athletes across divisions', function () {
    $division = Division::factory()->create();
    $a = Entry::factory()->for($division)->create();
    $b = Entry::factory()->for($division)->create();
    $person = Person::factory()->create(['organization_id' => $division->organization_id]);
    addToRoster($a, $person, 'athlete', 3);

    $this->post("/painel/entries/{$b->id}/roster", ['person_id' => $person->id, 'role' => 'athlete', 'shirt_number' => 3])
        ->assertSessionHasErrors('person_id');

    $this->post("/painel/entries/{$b->id}/roster", ['person_id' => $person->id, 'role' => 'coach'])->assertSessionHasNoErrors();

    $otherDivision = Entry::factory()->for(Division::factory()->create(['season_id' => $division->season_id]))->create();
    $this->post("/painel/entries/{$otherDivision->id}/roster", ['person_id' => $person->id, 'role' => 'athlete', 'shirt_number' => 3])
        ->assertSessionHasNoErrors();

    // Depois de removido da primeira equipe, pode entrar em outra da mesma divisão.
    RosterEntry::query()->where('entry_id', $a->id)->update(['status' => RosterStatus::Removed]);
    $this->post("/painel/entries/{$b->id}/roster", ['person_id' => $person->id, 'role' => 'athlete', 'shirt_number' => 3])
        ->assertSessionHasNoErrors();
});

it('updates the shirt number, role and status with the same rules', function () {
    $entry = Entry::factory()->create();
    $one = addToRoster($entry, shirt: 1);
    $two = addToRoster($entry, shirt: 2);

    $this->patch("/painel/roster/{$two->id}", ['role' => 'athlete', 'shirt_number' => 1, 'status' => 'active'])
        ->assertSessionHasErrors('shirt_number');

    $this->patch("/painel/roster/{$two->id}", ['role' => 'athlete', 'shirt_number' => 22, 'status' => 'active'])->assertSessionHas('success');
    expect($two->fresh()->shirt_number)->toBe(22);

    $this->patch("/painel/roster/{$one->id}", ['role' => 'coach', 'shirt_number' => 1, 'status' => 'active'])->assertSessionHas('success');
    expect($one->fresh()->role)->toBe(RosterRole::Coach)->and($one->fresh()->shirt_number)->toBeNull();
});

it('removes a person from the roster, keeping the link as removed when there is game history', function () {
    $entry = Entry::factory()->create();
    $clean = addToRoster($entry, shirt: 4);

    $this->delete("/painel/roster/{$clean->id}")->assertSessionHas('success');
    expect(RosterEntry::query()->whereKey($clean->id)->exists())->toBeFalse();

    $played = addToRoster($entry, shirt: 6);
    $game = Game::factory()->create(['home_entry_id' => $entry->id, 'division_id' => $entry->division_id, 'organization_id' => $entry->organization_id]);
    GameEvent::factory()->create([
        'game_id' => $game->id, 'organization_id' => $entry->organization_id, 'entry_id' => $entry->id,
        'type' => GameEventType::YellowCard, 'person_id' => $played->person_id,
    ]);

    $this->delete("/painel/roster/{$played->id}")->assertSessionHas('success');
    expect($played->fresh()->status)->toBe(RosterStatus::Removed);
});

it('shows the active roster size on the division page', function () {
    $entry = Entry::factory()->create();
    addToRoster($entry, shirt: 1);
    addToRoster($entry, shirt: 2)->update(['status' => RosterStatus::Removed]);

    $this->get("/painel/divisions/{$entry->division_id}")
        ->assertInertia(fn ($page) => $page->where('entries.0.roster_count', 1));
});

it('does not expose rosters or people of another organization', function () {
    $other = Organization::factory()->create();
    $entry = CurrentOrganization::using($other, fn () => Entry::factory()->create());
    $foreignPerson = Person::factory()->create(['organization_id' => $other->id]);
    $mine = Entry::factory()->create();

    $this->get("/painel/entries/{$entry->id}")->assertNotFound();
    $this->post("/painel/entries/{$entry->id}/roster", ['name' => 'X', 'role' => 'athlete'])->assertNotFound();
    $this->post("/painel/entries/{$mine->id}/roster", ['person_id' => $foreignPerson->id, 'role' => 'athlete'])->assertSessionHasErrors('person_id');
});

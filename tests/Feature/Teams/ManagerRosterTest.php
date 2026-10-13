<?php

use App\Models\User;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Mail\RegistrationMail;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Mail\TeamManagerInvitation;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterChange;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamManager;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Storage::fake('s3-private');
    Mail::fake();
});

/**
 * Jogo agendado para daqui a 2 dias, elenco de 14 atletas + 1 técnico no mandante e o gestor dele.
 *
 * @return array{game: Game, entry: Entry, away: Entry, hp: list<string>, coach: string, manager: User, season: Season}
 */
function rosterSetup(): array
{
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);

    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    $game->update(['scheduled_at' => now()->addDays(2)]);

    $entry = $game->homeEntry;
    $hp = RosterEntry::factory()->count(14)->sequence(fn ($s) => ['shirt_number' => $s->index + 1])->create(['entry_id' => $entry->id])->pluck('person_id')->all();
    $coach = RosterEntry::factory()->create(['entry_id' => $entry->id, 'role' => RosterRole::Coach, 'shirt_number' => null])->person_id;

    return [
        'game' => $game->fresh(),
        'entry' => $entry,
        'away' => $game->awayEntry,
        'hp' => $hp,
        'coach' => $coach,
        'manager' => managerFor($entry->team),
        'season' => $entry->division->season,
    ];
}

function rosterRow(Entry $entry, string $personId): RosterEntry
{
    return RosterEntry::query()->where('entry_id', $entry->id)->where('person_id', $personId)->firstOrFail();
}

function setRegistrationSettings(Season $season, array $settings): void
{
    $season->update(['registration' => $settings + ['min_athletes' => 2, 'max_athletes' => 30, 'require_documents' => false]]);
}

describe('shirt and nickname', function () {
    it('changes the shirt number following the roster rules and logs it', function () {
        ['entry' => $entry, 'hp' => $hp, 'manager' => $manager] = rosterSetup();
        $row = rosterRow($entry, $hp[0]);

        $this->actingAs($manager)->patch(route('manager.roster.update', $row), ['shirt_number' => 77])->assertSessionHas('success');

        expect($row->fresh()->shirt_number)->toBe(77);

        $change = RosterChange::query()->where('entry_id', $entry->id)->firstOrFail();
        expect($change->action)->toBe(RosterChange::SHIRT)
            ->and($change->details)->toMatchArray(['from' => 1, 'to' => 77])
            ->and($change->user_id)->toBe($manager->id);
    });

    it('refuses a shirt number already used by another active athlete', function () {
        ['entry' => $entry, 'hp' => $hp, 'manager' => $manager] = rosterSetup();

        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $hp[0])), ['shirt_number' => 2])->assertSessionHas('error');

        expect(rosterRow($entry, $hp[0])->shirt_number)->toBe(1)->and(RosterChange::query()->count())->toBe(0);
    });

    it('does not give a shirt number to the staff', function () {
        ['entry' => $entry, 'coach' => $coach, 'manager' => $manager] = rosterSetup();

        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $coach)), ['shirt_number' => 5])->assertSessionHas('error');
    });

    it('changes the nickname of a person who belongs only to the managed teams', function () {
        ['entry' => $entry, 'hp' => $hp, 'manager' => $manager] = rosterSetup();

        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $hp[0])), ['nickname' => '  Foguete '])->assertSessionHas('success');

        expect(Person::query()->findOrFail($hp[0])->nickname)->toBe('Foguete')
            ->and(RosterChange::query()->where('action', RosterChange::NICKNAME)->count())->toBe(1);
    });

    it('does not change the nickname of a person who is also active in a team of someone else', function () {
        ['entry' => $entry, 'away' => $away, 'hp' => $hp, 'manager' => $manager] = rosterSetup();
        RosterEntry::factory()->create(['entry_id' => $away->id, 'person_id' => $hp[0], 'shirt_number' => 40]);

        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $hp[0])), ['nickname' => 'Foguete'])->assertSessionHas('error');

        expect(Person::query()->findOrFail($hp[0])->nickname)->not->toBe('Foguete');
    });

    it('answers 404 for a manager of another team', function () {
        ['entry' => $entry, 'hp' => $hp] = rosterSetup();
        $stranger = managerFor(Team::factory()->create());
        $row = rosterRow($entry, $hp[0]);

        $this->actingAs($stranger)->patch(route('manager.roster.update', $row), ['shirt_number' => 77])->assertNotFound();
        $this->actingAs($stranger)->delete(route('manager.roster.destroy', $row))->assertNotFound();

        expect($row->fresh()->shirt_number)->toBe(1);
    });

    it('does not open to the panel staff', function () {
        ['entry' => $entry, 'hp' => $hp] = rosterSetup();

        $this->actingAs(panelUser(Role::Admin))->patch(route('manager.roster.update', rosterRow($entry, $hp[0])), ['shirt_number' => 77])->assertForbidden();
    });
});

describe('the roster window', function () {
    it('closes when the organization turns manager changes off', function () {
        ['entry' => $entry, 'hp' => $hp, 'manager' => $manager, 'season' => $season] = rosterSetup();
        setRegistrationSettings($season, ['roster_changes' => false]);

        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $hp[0])), ['shirt_number' => 77])->assertSessionHas('error');
        $this->actingAs($manager)->delete(route('manager.roster.destroy', rosterRow($entry, $hp[0])))->assertSessionHas('error');
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry))->assertSessionHas('error');

        $this->actingAs($manager)->get(route('manager.entries.show', $entry))
            ->assertInertia(fn ($page) => $page->where('window.closed_reason', fn ($reason) => str_contains($reason, 'não permite')));

        expect(Registration::query()->count())->toBe(0);
    });

    it('closes after the deadline date and when the season is over', function () {
        ['entry' => $entry, 'hp' => $hp, 'manager' => $manager, 'season' => $season] = rosterSetup();
        setRegistrationSettings($season, ['roster_changes_until' => now()->subDay()->toDateString()]);

        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $hp[0])), ['shirt_number' => 77])->assertSessionHas('error');

        setRegistrationSettings($season->fresh(), ['roster_changes_until' => now()->addDay()->toDateString()]);
        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $hp[0])), ['shirt_number' => 77])->assertSessionHas('success');

        $season->fresh()->update(['status' => SeasonStatus::Finished]);
        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $hp[1])), ['shirt_number' => 78])->assertSessionHas('error');
    });

    it('can be set by the organizer in the season registration rules', function () {
        ['season' => $season] = rosterSetup();

        $this->actingAs(panelUser(Role::Organizer))->patch(route('panel.seasons.registration.update', $season), [
            'min_athletes' => 5, 'max_athletes' => 20, 'require_documents' => true, 'roster_changes' => false, 'roster_changes_until' => '2030-01-31',
        ])->assertSessionHas('success');

        expect($season->fresh()->registrationSettings())->toMatchArray(['roster_changes' => false, 'roster_changes_until' => '2030-01-31']);
    });
});

describe('releasing from the roster', function () {
    it('removes the athlete from the roster and from the upcoming lineups, dropping their confirmation', function () {
        ['game' => $game, 'entry' => $entry, 'hp' => $hp, 'manager' => $manager] = rosterSetup();
        app(SaveGameLineup::class)->handle($game, $entry, array_slice($hp, 0, 11), array_slice($hp, 11, 3));
        GameTactic::query()->create(['organization_id' => $game->organization_id, 'game_id' => $game->id, 'entry_id' => $entry->id, 'formation' => '4-4-2', 'confirmed_at' => now()]);

        $this->actingAs($manager)->delete(route('manager.roster.destroy', rosterRow($entry, $hp[0])))->assertSessionHas('success');

        expect(RosterEntry::query()->where('entry_id', $entry->id)->where('person_id', $hp[0])->exists())->toBeFalse()
            ->and(GameLineup::query()->where('game_id', $game->id)->where('person_id', $hp[0])->exists())->toBeFalse()
            ->and(GameLineup::query()->where('game_id', $game->id)->where('entry_id', $entry->id)->count())->toBe(13)
            ->and(GameTactic::query()->firstOrFail()->isConfirmed())->toBeFalse()
            ->and(RosterChange::query()->where('action', RosterChange::RELEASED)->firstOrFail()->details)->toMatchArray(['kept_as_removed' => false]);
    });

    it('keeps the link as removed when the athlete already has game history', function () {
        ['game' => $game, 'entry' => $entry, 'hp' => $hp, 'manager' => $manager] = rosterSetup();
        app(SaveGameLineup::class)->handle($game, $entry, array_slice($hp, 0, 11));
        app(RecordGameEvent::class)->handle($game, GameEventType::YellowCard, $entry->id, $hp[0]);

        $this->actingAs($manager)->delete(route('manager.roster.destroy', rosterRow($entry, $hp[0])))->assertSessionHas('success');

        expect(rosterRow($entry, $hp[0])->status)->toBe(RosterStatus::Removed)
            ->and(RosterChange::query()->where('action', RosterChange::RELEASED)->firstOrFail()->details)->toMatchArray(['kept_as_removed' => true]);
    });

    it('does not release somebody who is already out of the roster', function () {
        ['entry' => $entry, 'hp' => $hp, 'manager' => $manager] = rosterSetup();
        $row = rosterRow($entry, $hp[0]);
        $row->update(['status' => RosterStatus::Removed]);

        $this->actingAs($manager)->delete(route('manager.roster.destroy', $row))->assertSessionHas('error');
    });
});

describe('supplement requests', function () {
    it('opens one supplement per entry and takes the manager to the request form', function () {
        ['entry' => $entry, 'manager' => $manager] = rosterSetup();

        $response = $this->actingAs($manager)->post(route('manager.entries.supplement', $entry));
        $registration = Registration::query()->firstOrFail();

        $response->assertRedirect($registration->manageUrl());
        expect($registration->isSupplement())->toBeTrue()
            ->and($registration->entry_id)->toBe($entry->id)
            ->and($registration->team_id)->toBe($entry->team_id)
            ->and($registration->status)->toBe(RegistrationStatus::Draft)
            ->and($registration->requested_by)->toBe($manager->id)
            ->and($registration->contact_email)->toBe(mb_strtolower($manager->email));

        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry))->assertRedirect($registration->manageUrl());

        expect(Registration::query()->count())->toBe(1)
            ->and(RosterChange::query()->where('action', RosterChange::SUPPLEMENT_REQUESTED)->count())->toBe(1);
    });

    it('renders the request form as a supplement, with the way back to the manager area', function () {
        ['entry' => $entry, 'manager' => $manager] = rosterSetup();
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry));
        $registration = Registration::query()->firstOrFail();

        $this->get($registration->manageUrl())->assertOk()->assertInertia(fn ($page) => $page
            ->where('registration.supplement', true)
            ->where('registration.back_url', route('manager.entries.show', $entry))
            ->where('registration.team_name', $entry->team->name));
    });

    it('answers 404 to another manager and needs the online registration feature', function () {
        ['entry' => $entry, 'manager' => $manager] = rosterSetup();
        $stranger = managerFor(Team::factory()->create());

        $this->actingAs($stranger)->post(route('manager.entries.supplement', $entry))->assertNotFound();

        $organization = CurrentOrganization::get();
        $organization->update(['plan' => Plan::Basic->value, 'features' => ['team_portal']]);
        CurrentOrganization::set($organization->fresh());

        // Escrita sem o recurso: volta com o aviso (a página, em GET, seria a tela de 403).
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry))->assertSessionHas('error');
        expect(Registration::query()->count())->toBe(0);
    });

    it('does not allow editing the team data of a supplement', function () {
        ['entry' => $entry, 'manager' => $manager] = rosterSetup();
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry));
        $registration = Registration::query()->firstOrFail();

        $this->patch("{$registration->manageUrl()}/equipe", ['team_name' => 'Outro nome', 'contact_name' => 'x', 'contact_phone' => null, 'team_short_name' => null, 'team_city' => null])
            ->assertSessionHas('error');

        expect($registration->fresh()->team_name)->toBe($entry->team->name);
    });

    it('needs at least one person and respects the maximum of the whole roster', function () {
        ['entry' => $entry, 'manager' => $manager, 'season' => $season] = rosterSetup();
        setRegistrationSettings($season, ['max_athletes' => 14]);
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry));
        $registration = Registration::query()->firstOrFail();

        $this->post("{$registration->manageUrl()}/enviar")->assertSessionHas('error');

        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Reforço Um', 'birth_date' => '1995-05-05', 'shirt_number' => 40]);
        $this->post("{$registration->manageUrl()}/enviar")->assertSessionHas('error'); // 14 + 1 > 14

        setRegistrationSettings($season->fresh(), ['max_athletes' => 15]);
        $this->post("{$registration->manageUrl()}/enviar")->assertSessionHas('success');
        expect($registration->fresh()->status)->toBe(RegistrationStatus::Submitted);
    });

    it('goes through the organizer review and adds the approved people to the existing roster', function () {
        ['entry' => $entry, 'manager' => $manager, 'hp' => $hp] = rosterSetup();
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry));
        $registration = Registration::query()->firstOrFail();

        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Reforço Um', 'birth_date' => '1995-05-05', 'shirt_number' => 40]);
        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Reforço Dois', 'birth_date' => '1996-06-06', 'shirt_number' => 41]);
        $this->post("{$registration->manageUrl()}/enviar")->assertSessionHas('success');

        $organizer = panelUser(Role::Organizer);
        $people = RegistrationPerson::query()->where('registration_id', $registration->id)->orderBy('name')->get();

        $this->actingAs($organizer)->get(route('panel.registrations.index'))->assertInertia(fn ($page) => $page->where('registrations.data.0.supplement', true));

        // Aprovar com pessoa pendente não passa.
        $this->actingAs($organizer)->post(route('panel.registrations.approve', $registration))->assertSessionHas('error');

        $this->actingAs($organizer)->patch(route('panel.registrations.people.review', [$registration, $people[0]]), ['review_status' => 'approved']);
        $this->actingAs($organizer)->patch(route('panel.registrations.people.review', [$registration, $people[1]]), ['review_status' => 'rejected', 'review_note' => 'Documento ilegível']);

        $teams = Team::query()->count();
        $entries = Entry::query()->count();

        $this->actingAs($organizer)->post(route('panel.registrations.approve', $registration))->assertSessionHas('success');

        $roster = RosterEntry::query()->where('entry_id', $entry->id)->where('status', RosterStatus::Active);
        expect($registration->fresh()->status)->toBe(RegistrationStatus::Approved)
            ->and((clone $roster)->count())->toBe(count($hp) + 2) // 14 atletas + técnico já existentes + 1 reforço
            ->and((clone $roster)->where('shirt_number', 40)->exists())->toBeTrue()
            ->and((clone $roster)->where('shirt_number', 41)->exists())->toBeFalse()
            ->and(Team::query()->count())->toBe($teams)
            ->and(Entry::query()->count())->toBe($entries)
            ->and(RosterChange::query()->where('action', RosterChange::SUPPLEMENT_APPROVED)->count())->toBe(1);

        Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail) => str_contains($mail->subjectLine, 'Reforços'));
    });

    it('refuses approval when a shirt number clashes, creating nobody', function () {
        ['entry' => $entry, 'manager' => $manager] = rosterSetup();
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry));
        $registration = Registration::query()->firstOrFail();

        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Reforço Um', 'birth_date' => '1995-05-05', 'shirt_number' => 40]);
        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Reforço Dois', 'birth_date' => '1996-06-06', 'shirt_number' => 5]);
        $this->post("{$registration->manageUrl()}/enviar");

        $organizer = panelUser(Role::Organizer);
        foreach (RegistrationPerson::query()->where('registration_id', $registration->id)->get() as $person) {
            $this->actingAs($organizer)->patch(route('panel.registrations.people.review', [$registration, $person]), ['review_status' => 'approved']);
        }

        $before = RosterEntry::query()->where('entry_id', $entry->id)->count();
        $this->actingAs($organizer)->post(route('panel.registrations.approve', $registration))->assertSessionHas('error');

        expect(RosterEntry::query()->where('entry_id', $entry->id)->count())->toBe($before)
            ->and($registration->fresh()->status)->toBe(RegistrationStatus::Submitted);
    });

    it('lists the requests and the change history on the roster screen', function () {
        ['entry' => $entry, 'hp' => $hp, 'manager' => $manager] = rosterSetup();
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry));
        $this->actingAs($manager)->patch(route('manager.roster.update', rosterRow($entry, $hp[0])), ['shirt_number' => 77]);

        $this->actingAs($manager)->get(route('manager.entries.show', $entry))->assertInertia(fn ($page) => $page
            ->where('window.closed_reason', null)
            ->has('supplements', 1)->where('supplements.0.status', 'draft')->where('supplements.0.url', fn ($url) => is_string($url))
            ->has('changes', 2));
    });
});

describe('inviting the registration contact as manager', function () {
    it('invites the contact of an approved registration, by explicit action of the organizer', function () {
        $team = Team::factory()->create();
        $registration = Registration::factory()->create(['status' => RegistrationStatus::Approved, 'team_id' => $team->id, 'contact_name' => 'Maria Responsável', 'contact_email' => 'maria@exemplo.com']);

        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.registrations.manager', $registration))->assertSessionHas('success');

        $user = User::query()->where('email', 'maria@exemplo.com')->firstOrFail();
        expect(TeamManager::query()->where('user_id', $user->id)->where('team_id', $team->id)->exists())->toBeTrue();
        Mail::assertQueued(TeamManagerInvitation::class, fn (TeamManagerInvitation $mail) => $mail->hasTo('maria@exemplo.com'));
    });

    it('does not invite for pending registrations nor for supplements', function () {
        $team = Team::factory()->create();
        $organizer = panelUser(Role::Organizer);

        $pending = Registration::factory()->create(['status' => RegistrationStatus::Submitted, 'team_id' => $team->id]);
        $supplement = Registration::factory()->create(['status' => RegistrationStatus::Approved, 'team_id' => $team->id, 'kind' => Registration::KIND_SUPPLEMENT]);

        $this->actingAs($organizer)->post(route('panel.registrations.manager', $pending))->assertSessionHas('error');
        $this->actingAs($organizer)->post(route('panel.registrations.manager', $supplement))->assertSessionHas('error');

        expect(TeamManager::query()->count())->toBe(0);
        Mail::assertNothingQueued();
    });

    it('is a write area of the organizer: the operator cannot', function () {
        $registration = Registration::factory()->create(['status' => RegistrationStatus::Approved, 'team_id' => Team::factory()->create()->id]);

        $this->actingAs(panelUser(Role::Operator))->post(route('panel.registrations.manager', $registration))->assertSessionHas('error');

        expect(TeamManager::query()->count())->toBe(0);
    });
});

describe('cards of the own roster', function () {
    it('shows the cards of the own team without the renew button', function () {
        ['entry' => $entry, 'manager' => $manager] = rosterSetup();

        $this->actingAs($manager)->get(route('manager.entries.cards', $entry))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Cards/Show')
                ->has('cards', 15)
                ->where('can_regenerate', false)
                ->where('back_url', route('manager.entries.show', $entry)));
    });

    it('answers 404 for the roster of another team and needs the cards feature', function () {
        ['entry' => $entry, 'away' => $away, 'manager' => $manager] = rosterSetup();

        $this->actingAs($manager)->get(route('manager.entries.cards', $away))->assertNotFound();

        $organization = CurrentOrganization::get();
        $organization->update(['plan' => Plan::Basic->value, 'features' => ['team_portal']]);
        CurrentOrganization::set($organization->fresh());

        $this->actingAs($manager)->get(route('manager.entries.cards', $entry))->assertForbidden();
    });

    it('keeps the panel cards with the renew button', function () {
        ['entry' => $entry, 'hp' => $hp] = rosterSetup();

        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.roster.card', rosterRow($entry, $hp[0])))
            ->assertInertia(fn ($page) => $page->where('can_regenerate', true));
    });
});

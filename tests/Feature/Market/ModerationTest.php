<?php

use App\Models\User;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Enums\ReportStatus;
use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Models\MarketReport;
use App\Modules\Registration\Models\Registration;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['market']]));
});

/** @return array{0: User, 1: MarketProfile} atleta completo, publicado e com e-mail verificado */
function modTarget(array $profile = []): array
{
    $athlete = panelUser(Role::Athlete);
    $athlete->forceFill(['name' => 'Fulano da Silva'])->save();

    return [$athlete, MarketProfile::query()->create([
        'user_id' => $athlete->getKey(),
        'status' => ProfileStatus::Published,
        'birth_date' => now()->subYears(27)->toDateString(),
        'sport' => 'football',
        'positions' => ['goalkeeper'],
        'city' => 'Ilha Comprida',
        'display_name' => 'Paredão',
        'contact_channel' => 'whatsapp',
        'contact_value' => '13999990000',
        'published_at' => now(),
        'expires_at' => now()->addDays(30),
        ...$profile,
    ])];
}

/** Gestor de uma equipe com inscrição confirmada, e um pedido de contato ACEITO de um atleta. */
function modAcceptedRequest(): array
{
    ['entries' => [$entry]] = fixtureDivision(1);
    $manager = managerFor($entry->team);
    [$athlete, $profile] = modTarget();
    $request = ContactRequest::query()->create([
        'profile_id' => $profile->getKey(),
        'team_id' => $entry->team_id,
        'requested_by' => $manager->getKey(),
        'message' => 'Venha jogar!',
        'status' => RequestStatus::Accepted,
        'expires_at' => now()->addDays(10),
    ]);

    return compact('entry', 'manager', 'athlete', 'profile', 'request');
}

/** Reforço sem exigir documentos (o fluxo de documentos já é testado na inscrição). */
function modDocumentsNotRequired(Entry $entry): void
{
    $entry->division->season->update(['registration' => ['min_athletes' => 2, 'max_athletes' => 30, 'require_documents' => false]]);
}

describe('reports', function () {
    it('lets the manager report a profile, once while it is open', function () {
        [, $profile] = modTarget();
        $manager = managerFor(Team::factory()->create());

        $this->actingAs($manager)->post(route('manager.market.report', $profile), ['reason' => 'Perfil com foto de outra pessoa.'])->assertSessionHas('success');
        $this->actingAs($manager)->post(route('manager.market.report', $profile), ['reason' => 'Outra vez o mesmo problema.'])->assertSessionHas('error');
        $this->actingAs($manager)->post(route('manager.market.report', $profile), ['reason' => 'curto'])->assertSessionHasErrors('reason');

        $report = MarketReport::query()->firstOrFail();
        expect(MarketReport::query()->count())->toBe(1)
            ->and($report->reporter_kind)->toBe('manager')
            ->and($report->team_id)->toBeNull()
            ->and($report->status)->toBe(ReportStatus::Open);
    });

    it('lets the athlete report a team that asked for contact, only on the own requests', function () {
        ['athlete' => $athlete, 'request' => $request, 'entry' => $entry] = modAcceptedRequest();
        [$stranger] = modTarget();

        $this->actingAs($stranger)->post(route('athlete.requests.report', $request), ['reason' => 'Mensagens inadequadas.'])->assertNotFound();
        $this->actingAs($athlete)->post(route('athlete.requests.report', $request), ['reason' => 'Mensagens inadequadas no contato.'])->assertSessionHas('success');

        $report = MarketReport::query()->firstOrFail();
        expect($report->reporter_kind)->toBe('athlete')
            ->and($report->team_id)->toBe($entry->team_id);
    });
});

describe('moderation panel', function () {
    it('is for who has the market permission, and nobody else, not even to read', function () {
        foreach ([Role::Admin, Role::Organizer] as $role) {
            $this->actingAs(panelUser($role))->get(route('panel.market.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Panel/Market/Index'));
        }

        foreach ([Role::Operator, Role::Viewer] as $role) {
            $this->actingAs(panelUser($role))->get(route('panel.market.index'))->assertForbidden();
        }

        $this->actingAs(panelUser(Role::Athlete))->get(route('panel.market.index'))->assertForbidden();
        $this->actingAs(managerFor(Team::factory()->create()))->get(route('panel.market.index'))->assertForbidden();
        expect(Role::Organizer->permissions())->toContain(Permission::MarketModerate)
            ->and(Role::Viewer->permissions())->not->toContain(Permission::MarketModerate);
    });

    it('needs the market released for the organization', function () {
        $organizer = panelUser(Role::Organizer);
        CurrentOrganization::get()->update(['opt_in_features' => null]);

        $this->actingAs($organizer)->get(route('panel.market.index'))->assertForbidden();
    });

    it('lists reports and profiles without contact or birth date', function () {
        [, $profile] = modTarget();
        $manager = managerFor(Team::factory()->create());
        $this->actingAs($manager)->post(route('manager.market.report', $profile), ['reason' => 'Perfil com foto de outra pessoa.']);

        $response = $this->actingAs(panelUser(Role::Organizer))->get(route('panel.market.index'));
        $props = $response->viewData('page')['props'];

        expect($props['totals'])->toMatchArray(['published' => 1, 'open_reports' => 1])
            ->and($props['reports'][0]['about'])->toBe('Jogador Paredão')
            ->and($props['profiles']['data'][0]['open_reports'])->toBe(1)
            ->and(json_encode($props))->not->toContain('13999990000')
            ->and(json_encode($props))->not->toContain('birth_date')
            ->and(json_encode($props))->not->toContain('contact_value');
    });

    it('filters the profiles by status', function () {
        modTarget();
        modTarget(['status' => ProfileStatus::Paused, 'display_name' => 'Pausado']);

        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.market.index', ['filter' => ['status' => 'paused']]))
            ->assertInertia(fn ($page) => $page->has('profiles.data', 1)->where('profiles.data.0.name', 'Pausado'));
    });

    it('searches and sorts the profiles on the server', function () {
        modTarget(['display_name' => 'Zeca Ilhéu', 'city' => 'Cananéia']);
        modTarget(['display_name' => 'Abel Costa', 'city' => 'Registro']);

        $organizer = panelUser(Role::Organizer);

        $this->actingAs($organizer)->get(route('panel.market.index', ['q' => 'cananéia']))
            ->assertInertia(fn ($page) => $page->has('profiles.data', 1)->where('profiles.data.0.name', 'Zeca Ilhéu'));
        $this->actingAs($organizer)->get(route('panel.market.index', ['q' => 'Zeca']))
            ->assertInertia(fn ($page) => $page->has('profiles.data', 1)->where('profiles.data.0.name', 'Zeca Ilhéu'));
        $this->actingAs($organizer)->get(route('panel.market.index', ['sort' => 'name', 'dir' => 'asc']))
            ->assertInertia(fn ($page) => $page->where('profiles.data.0.name', 'Abel Costa')->where('filters.sort', 'name'));
    });

    it('suspends a profile: out of the search, reason shown to the athlete, no publishing until reactivated', function () {
        [$athlete, $profile] = modTarget();
        $organizer = panelUser(Role::Organizer);
        $manager = managerFor(Team::factory()->create());

        $this->actingAs($organizer)->post(route('panel.market.suspend', $profile), ['reason' => 'Foto inadequada'])->assertSessionHas('success');

        $profile->refresh();
        expect($profile->status)->toBe(ProfileStatus::Suspended)->and($profile->suspended_reason)->toBe('Foto inadequada');
        expect($this->actingAs($manager)->get(route('manager.market.index'))->viewData('page')['props']['profiles']['data'])->toBe([]);
        $this->actingAs($athlete)->get(route('athlete.dashboard'))->assertInertia(fn ($page) => $page->where('profile.suspended_reason', 'Foto inadequada'));
        $this->actingAs($athlete)->post(route('athlete.publish'))->assertSessionHas('error');

        $this->actingAs($organizer)->post(route('panel.market.restore', $profile))->assertSessionHas('success');
        expect($profile->fresh()->status)->toBe(ProfileStatus::Paused)->and($profile->fresh()->suspended_reason)->toBeNull();
        $this->actingAs($organizer)->post(route('panel.market.restore', $profile))->assertSessionHas('error');
    });

    it('requires a reason to suspend and only for the permitted', function () {
        [, $profile] = modTarget();

        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.market.suspend', $profile), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs(panelUser(Role::Viewer))->post(route('panel.market.suspend', $profile), ['reason' => 'Qualquer motivo'])->assertSessionHas('error');
        expect($profile->fresh()->status)->toBe(ProfileStatus::Published);
    });

    it('resolves reports with a note', function () {
        [, $profile] = modTarget();
        $manager = managerFor(Team::factory()->create());
        $this->actingAs($manager)->post(route('manager.market.report', $profile), ['reason' => 'Perfil com foto de outra pessoa.']);
        $report = MarketReport::query()->firstOrFail();
        $organizer = panelUser(Role::Organizer);

        $this->actingAs($organizer)->patch(route('panel.market.reports.resolve', $report), ['outcome' => 'open'])->assertSessionHasErrors('outcome');
        $this->actingAs($organizer)->patch(route('panel.market.reports.resolve', $report), ['outcome' => 'dismissed', 'note' => 'Sem provas'])->assertSessionHas('success');

        $report->refresh();
        expect($report->status)->toBe(ReportStatus::Dismissed)
            ->and($report->note)->toBe('Sem provas')
            ->and($report->handled_by)->toBe($organizer->getKey())
            ->and($report->handled_at)->not->toBeNull();
    });

    it('changes the daily request limit, which the requests then obey', function () {
        $organizer = panelUser(Role::Organizer);
        $this->actingAs($organizer)->patch(route('panel.market.settings'), ['daily_requests' => 0])->assertSessionHasErrors('daily_requests');
        $this->actingAs($organizer)->patch(route('panel.market.settings'), ['daily_requests' => 1])->assertSessionHas('success');

        $team = Team::factory()->create();
        $manager = managerFor($team);
        [, $first] = modTarget();
        [, $second] = modTarget();

        $this->actingAs($manager)->post(route('manager.market.requests.store', $first), ['team_id' => $team->getKey(), 'message' => 'Venha jogar com a gente'])->assertSessionHas('success');
        $this->actingAs($manager)->post(route('manager.market.requests.store', $second), ['team_id' => $team->getKey(), 'message' => 'Venha jogar com a gente'])->assertSessionHas('error');
        $this->actingAs($organizer)->get(route('panel.market.index'))->assertInertia(fn ($page) => $page->where('settings.daily_requests', 1));
    });
});

describe('from the accepted contact to the roster', function () {
    it('opens the supplement of the team entry and links it to the request', function () {
        ['entry' => $entry, 'manager' => $manager, 'request' => $request] = modAcceptedRequest();

        $response = $this->actingAs($manager)->post(route('manager.market.requests.supplement', $request), ['entry_id' => $entry->getKey()]);

        $registration = Registration::query()->firstOrFail();
        $response->assertRedirect($registration->manageUrl());
        expect($registration->isSupplement())->toBeTrue()
            ->and($registration->entry_id)->toBe($entry->getKey())
            ->and($request->fresh()->registration_id)->toBe($registration->getKey());

        $props = $this->actingAs($manager)->get(route('manager.market.index'))->viewData('page')['props'];
        expect($props['my_requests'][0]['hiring']['registration_url'])->toBe($registration->manageUrl());
    });

    it('needs an accepted contact and an entry of the same team', function () {
        ['entry' => $entry, 'manager' => $manager, 'request' => $request] = modAcceptedRequest();
        $other = fixtureDivision(1)['entries'][0];
        managerFor($other->team, $manager);

        $request->update(['status' => RequestStatus::Pending]);
        $this->actingAs($manager)->post(route('manager.market.requests.supplement', $request), ['entry_id' => $entry->getKey()])->assertSessionHas('error');

        $request->update(['status' => RequestStatus::Accepted]);
        $this->actingAs($manager)->post(route('manager.market.requests.supplement', $request), ['entry_id' => $other->getKey()])->assertSessionHas('error');
        expect(Registration::query()->count())->toBe(0);
    });

    it('keeps other managers out', function () {
        ['entry' => $entry, 'request' => $request] = modAcceptedRequest();
        $stranger = managerFor(Team::factory()->create());

        $this->actingAs($stranger)->post(route('manager.market.requests.supplement', $request), ['entry_id' => $entry->getKey()])->assertNotFound();
    });

    it('marks the athlete as hired only when the organization approves the supplement', function () {
        ['entry' => $entry, 'manager' => $manager, 'athlete' => $athlete, 'profile' => $profile, 'request' => $request] = modAcceptedRequest();
        modDocumentsNotRequired($entry);
        $this->actingAs($manager)->post(route('manager.market.requests.supplement', $request), ['entry_id' => $entry->getKey()]);
        $registration = Registration::query()->firstOrFail();

        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Fulano da Silva', 'birth_date' => '1999-05-05', 'shirt_number' => 40]);
        $this->post("{$registration->manageUrl()}/enviar")->assertSessionHas('success');
        expect($profile->fresh()->status)->toBe(ProfileStatus::Published);

        $organizer = panelUser(Role::Organizer);
        $person = $registration->people()->firstOrFail();
        $this->actingAs($organizer)->patch(route('panel.registrations.people.review', [$registration, $person]), ['review_status' => 'approved']);
        $this->actingAs($organizer)->post(route('panel.registrations.approve', $registration))->assertSessionHas('success');

        expect($profile->fresh()->status)->toBe(ProfileStatus::Signed);
        expect($this->actingAs($manager)->get(route('manager.market.index'))->viewData('page')['props']['profiles']['data'])->toBe([]);

        // Sem equipe de novo: volta ao Mercado por conta própria.
        $athlete->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($athlete)->post(route('athlete.publish'))->assertSessionHas('success');
        expect($profile->fresh()->status)->toBe(ProfileStatus::Published);
    });

    it('does not mark anyone as hired when the supplement is not linked to a market contact', function () {
        ['profile' => $profile] = modAcceptedRequest();
        ['entries' => [$entry]] = fixtureDivision(1);
        $manager = managerFor($entry->team);
        modDocumentsNotRequired($entry);
        $this->actingAs($manager)->post(route('manager.entries.supplement', $entry));
        $registration = Registration::query()->where('entry_id', $entry->getKey())->firstOrFail();

        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Outra Pessoa', 'birth_date' => '1999-05-05', 'shirt_number' => 40]);
        $this->post("{$registration->manageUrl()}/enviar");
        $organizer = panelUser(Role::Organizer);
        $this->actingAs($organizer)->patch(route('panel.registrations.people.review', [$registration, $registration->people()->firstOrFail()]), ['review_status' => 'approved']);
        $this->actingAs($organizer)->post(route('panel.registrations.approve', $registration))->assertSessionHas('success');

        expect($profile->fresh()->status)->toBe(ProfileStatus::Published);
    });
});

<?php

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Mail\MarketNotice;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketBlock;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['market']]));
    Mail::fake();
});

/** @return array{0: App\Models\User, 1: MarketProfile} atleta com perfil publicado */
function contactTarget(array $profile = []): array
{
    $athlete = panelUser(Role::Athlete);
    $athlete->forceFill(['name' => 'Fulano da Silva', 'email' => fake()->unique()->safeEmail()])->save();

    $record = MarketProfile::query()->create([
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
    ]);

    return [$athlete, $record];
}

function askContact($test, $manager, MarketProfile $profile, Team $team, string $message = 'Queremos você no time!')
{
    return $test->actingAs($manager)->post(route('manager.market.requests.store', $profile), ['team_id' => $team->getKey(), 'message' => $message]);
}

function marketProps($response): array
{
    return $response->viewData('page')['props'];
}

describe('asking for contact', function () {
    it('creates a pending request for the athlete and e-mails them without any contact', function () {
        [$athlete, $profile] = contactTarget();
        $team = Team::factory()->create(['name' => 'Ilha FC']);
        $manager = managerFor($team);

        askContact($this, $manager, $profile, $team)->assertSessionHas('success');

        $request = ContactRequest::query()->firstOrFail();
        expect($request->status)->toBe(RequestStatus::Pending)
            ->and($request->team_id)->toBe($team->getKey())
            ->and($request->requested_by)->toBe($manager->getKey())
            ->and($request->expires_at->isSameDay(now()->addDays(14)))->toBeTrue();

        Mail::assertQueued(MarketNotice::class, fn (MarketNotice $mail) => $mail->hasTo($athlete->email)
            && str_contains($mail->subjectLine, 'Ilha FC')
            && ! str_contains(json_encode($mail->lines), $manager->email));
    });

    it('only asks in the name of a team the manager manages', function () {
        [, $profile] = contactTarget();
        $mine = Team::factory()->create();
        $other = Team::factory()->create();
        $manager = managerFor($mine);

        askContact($this, $manager, $profile, $other)->assertSessionHasErrors('team_id');
        expect(ContactRequest::query()->count())->toBe(0);
    });

    it('requires a message', function () {
        [, $profile] = contactTarget();
        $team = Team::factory()->create();

        askContact($this, managerFor($team), $profile, $team, 'oi')->assertSessionHasErrors('message');
    });

    it('is only for managers of a organization with the market', function () {
        [, $profile] = contactTarget();
        $team = Team::factory()->create();

        askContact($this, panelUser(Role::Admin), $profile, $team)->assertForbidden();

        $manager = managerFor($team);
        CurrentOrganization::get()->update(['opt_in_features' => null]);
        askContact($this, $manager, $profile, $team)->assertForbidden();
    });

    it('refuses profiles that are not published or are out of the deadline', function (Closure $profileState) {
        [, $profile] = contactTarget($profileState());
        $team = Team::factory()->create();

        askContact($this, managerFor($team), $profile, $team)->assertSessionHas('error');
        expect(ContactRequest::query()->count())->toBe(0);
    })->with([
        'pausado' => [fn () => ['status' => ProfileStatus::Paused]],
        'contratado' => [fn () => ['status' => ProfileStatus::Signed]],
        'vencido' => [fn () => ['expires_at' => now()->subDay()]],
    ]);

    it('allows one pending request per athlete and team, and none after an answer', function () {
        [$athlete, $profile] = contactTarget();
        $team = Team::factory()->create();
        $manager = managerFor($team);

        askContact($this, $manager, $profile, $team)->assertSessionHas('success');
        askContact($this, $manager, $profile, $team)->assertSessionHas('error');
        expect(ContactRequest::query()->count())->toBe(1);

        $this->actingAs($athlete)->post(route('athlete.requests.decline', ContactRequest::query()->firstOrFail()));
        askContact($this, $manager, $profile, $team)->assertSessionHas('error');
        expect(ContactRequest::query()->count())->toBe(1);
    });

    it('lets a team ask again after the request expired or was withdrawn', function () {
        [, $profile] = contactTarget();
        $team = Team::factory()->create();
        $manager = managerFor($team);

        askContact($this, $manager, $profile, $team);
        ContactRequest::query()->update(['expires_at' => now()->subDay()]);
        askContact($this, $manager, $profile, $team)->assertSessionHas('success');
        expect(ContactRequest::query()->count())->toBe(2);
    });

    it('limits the requests per team per day', function () {
        CurrentOrganization::get()->update(['settings' => ['market' => ['daily_requests' => 2]]]);
        $team = Team::factory()->create();
        $manager = managerFor($team);

        foreach (range(1, 3) as $position) {
            [, $profile] = contactTarget();
            $response = askContact($this, $manager, $profile, $team);
            $position < 3 ? $response->assertSessionHas('success') : $response->assertSessionHas('error');
        }

        expect(ContactRequest::query()->count())->toBe(2);

        $this->travel(1)->days();
        [, $profile] = contactTarget();
        askContact($this, $manager, $profile, $team)->assertSessionHas('success');
    });

    it('does not e-mail when the organization turned the notice off', function () {
        CurrentOrganization::get()->update(['settings' => ['communications' => ['market_contact_request' => false]]]);
        [, $profile] = contactTarget();
        $team = Team::factory()->create();

        askContact($this, managerFor($team), $profile, $team)->assertSessionHas('success');

        Mail::assertNothingQueued();
    });
});

describe('answering', function () {
    it('shows the contact to the requesting manager only after the athlete accepts', function () {
        [$athlete, $profile] = contactTarget();
        $team = Team::factory()->create(['name' => 'Ilha FC']);
        $manager = managerFor($team);
        askContact($this, $manager, $profile, $team);

        $pending = marketProps($this->actingAs($manager)->get(route('manager.market.index')));
        expect($pending['profiles']['data'][0]['requests'][0]['status'])->toBe('pending')
            ->and($pending['profiles']['data'][0]['requests'][0]['contact'])->toBeNull()
            ->and(json_encode($pending))->not->toContain('13999990000');

        $this->actingAs($athlete)->post(route('athlete.requests.accept', ContactRequest::query()->firstOrFail()))->assertSessionHas('success');

        $accepted = marketProps($this->actingAs($manager)->get(route('manager.market.index')));
        expect($accepted['profiles']['data'][0]['requests'][0]['contact'])->toMatchArray(['channel' => 'WhatsApp', 'value' => '13999990000', 'link' => 'https://wa.me/5513999990000'])
            ->and($accepted['my_requests'][0]['contact']['value'])->toBe('13999990000')
            ->and($accepted['my_requests'][0]['name'])->toBe('Paredão');

        Mail::assertQueued(MarketNotice::class, fn (MarketNotice $mail) => $mail->hasTo($manager->email)
            && str_contains($mail->headline, 'aceitou')
            && ! str_contains(json_encode($mail->lines), '13999990000'));
    });

    it('keeps the contact hidden after a refusal', function () {
        [$athlete, $profile] = contactTarget();
        $team = Team::factory()->create();
        $manager = managerFor($team);
        askContact($this, $manager, $profile, $team);

        $this->actingAs($athlete)->post(route('athlete.requests.decline', ContactRequest::query()->firstOrFail()));

        $props = marketProps($this->actingAs($manager)->get(route('manager.market.index')));
        expect($props['my_requests'][0]['status'])->toBe('declined')
            ->and($props['my_requests'][0]['contact'])->toBeNull()
            ->and(json_encode($props))->not->toContain('13999990000');
    });

    it('does not show the contact or the requests of a team to the manager of another team', function () {
        [$athlete, $profile] = contactTarget();
        $teamA = Team::factory()->create();
        $managerA = managerFor($teamA);
        $managerB = managerFor(Team::factory()->create());
        askContact($this, $managerA, $profile, $teamA);
        $this->actingAs($athlete)->post(route('athlete.requests.accept', ContactRequest::query()->firstOrFail()));

        $props = marketProps($this->actingAs($managerB)->get(route('manager.market.index')));

        expect($props['my_requests'])->toBe([])
            ->and($props['profiles']['data'][0]['requests'])->toBe([])
            ->and(json_encode($props))->not->toContain('13999990000');
    });

    it('answers only requests addressed to the own profile', function () {
        [, $profile] = contactTarget();
        [$stranger] = contactTarget();
        $team = Team::factory()->create();
        askContact($this, managerFor($team), $profile, $team);
        $request = ContactRequest::query()->firstOrFail();

        $this->actingAs($stranger)->post(route('athlete.requests.accept', $request))->assertNotFound();
        $this->actingAs($stranger)->post(route('athlete.requests.decline', $request))->assertNotFound();
        $this->actingAs($stranger)->post(route('athlete.requests.block', $request))->assertNotFound();
        expect($request->fresh()->status)->toBe(RequestStatus::Pending);
    });

    it('does not accept an expired or already answered request', function () {
        [$athlete, $profile] = contactTarget();
        $team = Team::factory()->create();
        askContact($this, managerFor($team), $profile, $team);
        $request = ContactRequest::query()->firstOrFail();

        $request->update(['expires_at' => now()->subHour()]);
        $this->actingAs($athlete)->post(route('athlete.requests.accept', $request))->assertSessionHas('error');
        expect($request->fresh()->status)->toBe(RequestStatus::Pending);

        $request->update(['expires_at' => now()->addDay(), 'status' => RequestStatus::Declined]);
        $this->actingAs($athlete)->post(route('athlete.requests.accept', $request))->assertSessionHas('error');
        expect($request->fresh()->status)->toBe(RequestStatus::Declined);
    });

    it('lists the requests in the athlete area', function () {
        [$athlete, $profile] = contactTarget();
        $team = Team::factory()->create(['name' => 'Ilha FC']);
        askContact($this, managerFor($team), $profile, $team, 'Venha jogar!');

        $this->actingAs($athlete)->get(route('athlete.dashboard'))->assertInertia(fn ($page) => $page
            ->has('requests', 1)
            ->where('requests.0.team', 'Ilha FC')
            ->where('requests.0.message', 'Venha jogar!')
            ->where('requests.0.status', 'pending'));
    });
});

describe('withdrawing', function () {
    it('lets the manager cancel a request, and revoke an accepted one', function () {
        [$athlete, $profile] = contactTarget();
        $team = Team::factory()->create();
        $manager = managerFor($team);
        askContact($this, $manager, $profile, $team);
        $request = ContactRequest::query()->firstOrFail();
        $this->actingAs($athlete)->post(route('athlete.requests.accept', $request));

        $this->actingAs($manager)->delete(route('manager.market.requests.destroy', $request))->assertSessionHas('success');

        expect($request->fresh()->status)->toBe(RequestStatus::Withdrawn);
        $props = marketProps($this->actingAs($manager)->get(route('manager.market.index')));
        expect(json_encode($props))->not->toContain('13999990000');
    });

    it('is a 404 for the manager of another team', function () {
        [, $profile] = contactTarget();
        $team = Team::factory()->create();
        askContact($this, managerFor($team), $profile, $team);
        $other = managerFor(Team::factory()->create());

        $this->actingAs($other)->delete(route('manager.market.requests.destroy', ContactRequest::query()->firstOrFail()))->assertNotFound();
    });
});

describe('blocking', function () {
    it('declines the open requests, hides the profile and refuses new ones', function () {
        [$athlete, $profile] = contactTarget();
        $team = Team::factory()->create();
        $manager = managerFor($team);
        askContact($this, $manager, $profile, $team);
        $request = ContactRequest::query()->firstOrFail();
        $this->actingAs($athlete)->post(route('athlete.requests.accept', $request));

        $this->actingAs($athlete)->post(route('athlete.requests.block', $request))->assertSessionHas('success');

        expect($request->fresh()->status)->toBe(RequestStatus::Declined)
            ->and(MarketBlock::query()->where('profile_id', $profile->getKey())->where('team_id', $team->getKey())->exists())->toBeTrue();

        $props = marketProps($this->actingAs($manager)->get(route('manager.market.index')));
        expect($props['profiles']['data'])->toBe([])
            ->and(json_encode($props))->not->toContain('13999990000');

        askContact($this, $manager, $profile, $team)->assertSessionHas('error');
    });

    it('keeps the profile for a manager who still has another team that is not blocked', function () {
        [$athlete, $profile] = contactTarget();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $manager = managerFor($teamA);
        managerFor($teamB, $manager);
        askContact($this, $manager, $profile, $teamA);
        $this->actingAs($athlete)->post(route('athlete.requests.block', ContactRequest::query()->firstOrFail()));

        $props = marketProps($this->actingAs($manager)->get(route('manager.market.index')));

        expect($props['profiles']['data'])->toHaveCount(1)
            ->and(collect($props['profiles']['data'][0]['teams_available'])->pluck('id')->all())->toBe([$teamB->getKey()]);
    });

    it('can be undone only by the owner', function () {
        [$athlete, $profile] = contactTarget();
        [$stranger] = contactTarget();
        $team = Team::factory()->create();
        $block = MarketBlock::query()->create(['profile_id' => $profile->getKey(), 'team_id' => $team->getKey()]);

        $this->actingAs($stranger)->delete(route('athlete.blocks.destroy', $block))->assertNotFound();
        $this->actingAs($athlete)->delete(route('athlete.blocks.destroy', $block))->assertSessionHas('success');

        expect(MarketBlock::query()->count())->toBe(0);
    });
});

describe('expiration command', function () {
    it('expires old requests, pauses stale profiles and warns once about the ones about to expire', function () {
        [, $stale] = contactTarget(['expires_at' => now()->subDay()]);
        [$soon, $soonProfile] = contactTarget(['expires_at' => now()->addDays(3)]);
        [, $far] = contactTarget(['expires_at' => now()->addDays(30)]);

        $team = Team::factory()->create();
        ContactRequest::query()->create(['profile_id' => $far->getKey(), 'team_id' => $team->getKey(), 'requested_by' => managerFor($team)->getKey(), 'message' => 'Oi, tudo bem?', 'status' => RequestStatus::Pending, 'expires_at' => now()->subHour()]);

        $this->artisan('market:expire')->assertSuccessful();

        expect(ContactRequest::query()->firstOrFail()->status)->toBe(RequestStatus::Expired)
            ->and($stale->fresh()->status)->toBe(ProfileStatus::Paused)
            ->and($soonProfile->fresh()->status)->toBe(ProfileStatus::Published)
            ->and($soonProfile->fresh()->expiry_notice_at)->not->toBeNull()
            ->and($far->fresh()->expiry_notice_at)->toBeNull();
        Mail::assertQueued(MarketNotice::class, 1);
        Mail::assertQueued(MarketNotice::class, fn (MarketNotice $mail) => $mail->hasTo($soon->email));

        $this->artisan('market:expire')->assertSuccessful();
        Mail::assertQueued(MarketNotice::class, 1); // não repete no mesmo ciclo
    });

    it('does not warn when the organization turned the notice off', function () {
        CurrentOrganization::get()->update(['settings' => ['communications' => ['market_profile_expiring' => false]]]);
        contactTarget(['expires_at' => now()->addDays(2)]);

        $this->artisan('market:expire')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('warns again after the athlete renews', function () {
        [$athlete, $profile] = contactTarget(['expires_at' => now()->addDays(2)]);
        $athlete->forceFill(['email_verified_at' => now()])->save();
        $profile->update(['sport' => 'football', 'positions' => ['goalkeeper'], 'city' => 'X', 'contact_channel' => 'email', 'contact_value' => 'a@b.com']);

        $this->artisan('market:expire');
        $this->actingAs($athlete)->post(route('athlete.publish'));

        expect($profile->fresh()->expiry_notice_at)->toBeNull();
    });
});

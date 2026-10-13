<?php

use App\Models\User;
use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['market']]));
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://signed.test/{$path}?sig=1");
});

/** Atleta já cadastrado (papel `athlete` + perfil em rascunho) na organização atual. */
function marketAthlete(array $profile = [], array $user = []): array
{
    $athlete = panelUser(Role::Athlete);

    if ($user !== []) {
        $athlete->forceFill($user)->save();
    }

    $record = MarketProfile::query()->create(['user_id' => $athlete->getKey(), 'birth_date' => now()->subYears(25)->toDateString(), ...$profile]);

    return [$athlete, $record];
}

/** Perfil completo o bastante para publicar. */
function completeMarketProfile(): array
{
    return ['sport' => 'football', 'positions' => ['goalkeeper'], 'city' => 'Ilha Comprida', 'contact_channel' => 'email', 'contact_value' => 'atleta@example.com'];
}

function signupPayload(array $overrides = []): array
{
    return [
        'name' => 'Carlos Atleta',
        'email' => 'carlos@example.com',
        'birth_date' => now()->subYears(20)->toDateString(),
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
        'accept' => true,
        ...$overrides,
    ];
}

describe('on-demand feature', function () {
    it('hides signup and the athlete area until the platform releases the market', function () {
        CurrentOrganization::get()->update(['opt_in_features' => null]);

        $this->get(route('market.signup'))->assertNotFound();
        $this->post(route('market.signup.store'), signupPayload())->assertNotFound();

        [$athlete] = marketAthlete();
        $this->actingAs($athlete)->get(route('athlete.dashboard'))->assertForbidden();
    });

    it('is not part of any plan', function () {
        CurrentOrganization::get()->update(['opt_in_features' => null, 'plan' => 'pro', 'features' => null]);

        expect(CurrentOrganization::has(Feature::Market))->toBeFalse();
    });
});

describe('signup', function () {
    it('creates the athlete with a draft profile, signs them in and asks for e-mail confirmation', function () {
        $this->get(route('market.signup'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn ($page) => $page->component('Market/Register')->where('min_age', 18));

        $this->post(route('market.signup.store'), signupPayload())->assertRedirect(route('athlete.dashboard'));

        $user = User::query()->where('email', 'carlos@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);

        expect($user->hasVerifiedEmail())->toBeFalse()
            ->and($user->role())->toBe(Role::Athlete);

        $profile = MarketProfile::query()->where('user_id', $user->getKey())->firstOrFail();
        expect($profile->status)->toBe(ProfileStatus::Draft)
            ->and($profile->organization_id)->toBe(CurrentOrganization::id());
    });

    it('refuses minors, accepting exactly 18', function () {
        $this->post(route('market.signup.store'), signupPayload(['birth_date' => now()->subYears(18)->addDay()->toDateString()]))->assertSessionHasErrors('birth_date');
        expect(User::query()->where('email', 'carlos@example.com')->exists())->toBeFalse();

        $this->post(route('market.signup.store'), signupPayload(['birth_date' => now()->subYears(18)->toDateString()]))->assertRedirect(route('athlete.dashboard'));
    });

    it('requires accepting the terms and refuses an e-mail that already has an account', function () {
        $this->post(route('market.signup.store'), signupPayload(['accept' => false]))->assertSessionHasErrors('accept');

        User::factory()->create(['email' => 'carlos@example.com']);
        $this->post(route('market.signup.store'), signupPayload())->assertSessionHasErrors('email');
    });

    it('does not count athletes in the user limit nor list them in the users screen', function () {
        CurrentOrganization::get()->update(['limits' => ['max_users' => 1]]);
        marketAthlete();
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->get(route('panel.users.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('members', 1)->where('members.0.role', 'admin'));
    });
});

describe('area access', function () {
    it('sends the athlete to the athlete area and keeps them out of the other panels', function () {
        [$athlete] = marketAthlete();

        $this->actingAs($athlete)->get('/dashboard')->assertRedirect(route('athlete.dashboard'));
        $this->actingAs($athlete)->get(route('panel.dashboard'))->assertForbidden();
        $this->actingAs($athlete)->get(route('manager.dashboard'))->assertForbidden();
        $this->actingAs($athlete)->get(route('panel.users.index'))->assertForbidden();
        $this->actingAs($athlete)->get(route('athlete.dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Athlete/Dashboard')->where('profile.status', 'draft')->where('email_verified', true));
    });

    it('keeps everyone else out of the athlete area', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('athlete.dashboard'))->assertForbidden();
        $this->actingAs(managerFor(Team::factory()->create()))->get(route('athlete.dashboard'))->assertForbidden();
    });

    it('does not let the athlete reach the profile of another one', function () {
        [$first, $firstProfile] = marketAthlete(['city' => 'Registro']);
        [$second] = marketAthlete();

        $this->actingAs($second)->put(route('athlete.profile.update'), ['city' => 'Cananéia'])->assertRedirect();

        expect($firstProfile->fresh()->city)->toBe('Registro');
    });
});

describe('profile', function () {
    it('saves the profile with sport positions, normalized contact and cleaned availability', function () {
        [$athlete, $profile] = marketAthlete();

        $this->actingAs($athlete)->put(route('athlete.profile.update'), [
            'sport' => 'football',
            'positions' => ['goalkeeper', 'goalkeeper'],
            'dominant_foot' => 'left',
            'height_cm' => 188,
            'level' => 'amateur',
            'bio' => 'Goleiro com experiência.',
            'availability' => ['sat' => ['morning', 'evening'], 'mon' => [], 'xyz' => ['morning']],
            'city' => 'Ilha Comprida',
            'contact_channel' => 'whatsapp',
            'contact_value' => '(13) 99999-0000',
        ])->assertSessionHasNoErrors();

        $profile->refresh();
        expect($profile->positions)->toBe(['goalkeeper'])
            ->and($profile->availability)->toBe(['sat' => ['morning', 'evening']])
            ->and($profile->contact_value)->toBe('13999990000')
            ->and($profile->status)->toBe(ProfileStatus::Draft);
    });

    it('keeps the contact encrypted at rest', function () {
        [$athlete, $profile] = marketAthlete(completeMarketProfile());

        $raw = DB::table('market_profiles')->where('id', $profile->getKey())->value('contact_value');

        expect($raw)->not->toContain('atleta@example.com')
            ->and($profile->fresh()->contact_value)->toBe('atleta@example.com');
    });

    it('refuses unknown availability periods', function () {
        [$athlete] = marketAthlete();

        $this->actingAs($athlete)->put(route('athlete.profile.update'), ['availability' => ['sat' => ['dawn']]])->assertSessionHasErrors('availability.sat.0');
    });

    it('only accepts positions of the chosen sport', function () {
        [$athlete] = marketAthlete();

        $this->actingAs($athlete)->put(route('athlete.profile.update'), ['sport' => 'volleyball', 'positions' => ['goalkeeper']])->assertSessionHasErrors('positions.0');
        $this->actingAs($athlete)->put(route('athlete.profile.update'), ['sport' => 'volleyball', 'positions' => ['libero']])->assertSessionHasNoErrors();
    });

    it('validates the contact according to the channel', function () {
        [$athlete] = marketAthlete();

        $this->actingAs($athlete)->put(route('athlete.profile.update'), ['contact_channel' => 'whatsapp', 'contact_value' => '123'])->assertSessionHasErrors('contact_value');
        $this->actingAs($athlete)->put(route('athlete.profile.update'), ['contact_channel' => 'email', 'contact_value' => 'não é e-mail'])->assertSessionHasErrors('contact_value');
    });

    it('stores the photo only on the private disk and shows it by signed url', function () {
        [$athlete, $profile] = marketAthlete();

        $this->actingAs($athlete)->post(route('athlete.photo.store'), ['photo' => UploadedFile::fake()->image('foto.jpg', 600, 400)])
            ->assertSessionHas('success', 'Foto atualizada.');

        $profile->refresh();
        expect($profile->photo_path)->toStartWith("organizations/{$profile->organization_id}/market/{$profile->getKey()}/photo-");
        Storage::disk('s3-private')->assertExists("{$profile->photo_path}-400.webp");
        Storage::disk('s3-private')->assertExists("{$profile->photo_path}-96.webp");

        $this->actingAs($athlete)->get(route('athlete.dashboard'))->assertInertia(fn ($page) => $page->where('profile.photo_url', fn ($url) => str_contains($url, 'sig=1')));

        $old = $profile->photo_path;
        $this->actingAs($athlete)->delete(route('athlete.photo.destroy'));
        Storage::disk('s3-private')->assertMissing("{$old}-400.webp");
        expect($profile->fresh()->photo_path)->toBeNull();
    });
});

describe('publishing', function () {
    it('does not publish before the e-mail is verified', function () {
        [$athlete, $profile] = marketAthlete(completeMarketProfile(), ['email_verified_at' => null]);

        $this->actingAs($athlete)->post(route('athlete.publish'))->assertSessionHas('error');
        expect($profile->fresh()->status)->toBe(ProfileStatus::Draft);
    });

    it('does not publish an incomplete profile', function () {
        [$athlete, $profile] = marketAthlete(['sport' => 'football']);

        $this->actingAs($athlete)->post(route('athlete.publish'))->assertSessionHas('error', fn (string $message) => str_contains($message, 'posição') && str_contains($message, 'cidade'));
        expect($profile->fresh()->status)->toBe(ProfileStatus::Draft);
    });

    it('does not publish a minor, even if one slipped in', function () {
        [$athlete, $profile] = marketAthlete([...completeMarketProfile(), 'birth_date' => now()->subYears(17)->toDateString()]);

        $this->actingAs($athlete)->post(route('athlete.publish'))->assertSessionHas('error');
        expect($profile->fresh()->status)->toBe(ProfileStatus::Draft);
    });

    it('publishes, renews the 60 day deadline, pauses and publishes again', function () {
        [$athlete, $profile] = marketAthlete(completeMarketProfile());

        $this->actingAs($athlete)->post(route('athlete.publish'))->assertSessionHas('success');
        $profile->refresh();
        $publishedAt = $profile->published_at;
        expect($profile->status)->toBe(ProfileStatus::Published)
            ->and($profile->expires_at->isSameDay(now()->addDays(60)))->toBeTrue();

        $this->travel(10)->days();
        $this->actingAs($athlete)->post(route('athlete.publish'));
        $profile->refresh();
        expect($profile->published_at->equalTo($publishedAt))->toBeTrue()
            ->and($profile->expires_at->isSameDay(now()->addDays(60)))->toBeTrue();

        $this->actingAs($athlete)->post(route('athlete.pause'));
        expect($profile->fresh()->status)->toBe(ProfileStatus::Paused);

        $this->actingAs($athlete)->post(route('athlete.publish'));
        expect($profile->fresh()->status)->toBe(ProfileStatus::Published);
    });

    it('refuses to publish a suspended or signed profile', function (ProfileStatus $status) {
        [$athlete, $profile] = marketAthlete([...completeMarketProfile(), 'status' => $status]);

        $this->actingAs($athlete)->post(route('athlete.publish'))->assertSessionHas('error');
        expect($profile->fresh()->status)->toBe($status);
    })->with([ProfileStatus::Suspended, ProfileStatus::Signed]);

    it('pauses profiles that were not revalidated in time', function () {
        [, $stale] = marketAthlete([...completeMarketProfile(), 'status' => ProfileStatus::Published, 'expires_at' => now()->subDay()]);
        [, $fresh] = marketAthlete([...completeMarketProfile(), 'status' => ProfileStatus::Published, 'expires_at' => now()->addDays(5)]);

        $this->artisan('market:expire')->assertSuccessful();

        expect($stale->fresh()->status)->toBe(ProfileStatus::Paused)
            ->and($fresh->fresh()->status)->toBe(ProfileStatus::Published);
    });
});

describe('deleting the account', function () {
    it('needs the password and removes the profile, the photo and the user', function () {
        [$athlete, $profile] = marketAthlete(completeMarketProfile());
        $this->actingAs($athlete)->post(route('athlete.photo.store'), ['photo' => UploadedFile::fake()->image('foto.jpg', 300, 300)]);
        $photo = $profile->fresh()->photo_path;

        $this->actingAs($athlete)->delete(route('athlete.destroy'), ['password' => 'errada'])->assertSessionHasErrors('password');
        expect(MarketProfile::query()->count())->toBe(1);

        $this->actingAs($athlete)->delete(route('athlete.destroy'), ['password' => 'password'])->assertRedirect('/');

        expect(MarketProfile::query()->count())->toBe(0)
            ->and(Membership::query()->where('user_id', $athlete->getKey())->exists())->toBeFalse()
            ->and(DB::table('team_managers')->where('user_id', $athlete->getKey())->exists())->toBeFalse()
            ->and(User::query()->whereKey($athlete->getKey())->exists())->toBeFalse();
        Storage::disk('s3-private')->assertMissing("{$photo}-400.webp");
    });
});

describe('help', function () {
    it('shows the athlete only the athlete articles', function () {
        [$athlete] = marketAthlete();

        $this->actingAs($athlete)->get(route('athlete.help.index'))->assertOk();
        $this->actingAs($athlete)->get(route('athlete.help.show', 'meu-perfil-no-mercado'))->assertOk();
        $this->actingAs($athlete)->get(route('athlete.help.show', 'primeiros-passos'))->assertNotFound();
        $this->actingAs(panelUser(Role::Admin))->get(route('athlete.help.index'))->assertForbidden();
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.help.show', 'meu-perfil-no-mercado'))->assertNotFound();
    });
});

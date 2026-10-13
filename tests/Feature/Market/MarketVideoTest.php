<?php

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Models\MarketMedia;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['market']]));
});

function videoAthlete(array $profile = []): array
{
    $athlete = panelUser(Role::Athlete);

    return [$athlete, MarketProfile::query()->create([
        'user_id' => $athlete->getKey(),
        'birth_date' => now()->subYears(25)->toDateString(),
        'status' => ProfileStatus::Published,
        'sport' => 'football',
        'positions' => ['goalkeeper'],
        'city' => 'Ilha Comprida',
        'display_name' => 'Paredão',
        'published_at' => now(),
        'expires_at' => now()->addDays(30),
        ...$profile,
    ])];
}

describe('the athlete videos', function () {
    it('adds a link as normalized provider and id, never a file', function () {
        [$athlete, $profile] = videoAthlete();

        $this->actingAs($athlete)->post(route('athlete.videos.store'), ['url' => 'https://youtu.be/dQw4w9WgXcQ?si=abc'])->assertSessionHas('success');

        $video = MarketMedia::query()->firstOrFail();
        expect($video->profile_id)->toBe($profile->getKey())
            ->and($video->kind)->toBe('video_link')
            ->and($video->provider)->toBe('youtube')
            ->and($video->external_id)->toBe('dQw4w9WgXcQ')
            ->and($video->url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    });

    it('refuses links outside the allowed providers', function () {
        [$athlete] = videoAthlete();

        $this->actingAs($athlete)->post(route('athlete.videos.store'), ['url' => 'https://example.com/video.mp4'])->assertSessionHasErrors('url');
        $this->actingAs($athlete)->post(route('athlete.videos.store'), ['url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->actingAs($athlete)->post(route('athlete.videos.store'), [])->assertSessionHasErrors('url');

        expect(MarketMedia::query()->count())->toBe(0);
    });

    it('allows at most three videos and no repeats', function () {
        [$athlete] = videoAthlete();
        $ids = ['aaaaaaaaaaa', 'bbbbbbbbbbb', 'ccccccccccc'];

        foreach ($ids as $id) {
            $this->actingAs($athlete)->post(route('athlete.videos.store'), ['url' => "https://www.youtube.com/watch?v={$id}"])->assertSessionHasNoErrors();
        }

        $this->actingAs($athlete)->post(route('athlete.videos.store'), ['url' => 'https://www.youtube.com/watch?v=ddddddddddd'])->assertSessionHasErrors('url');
        $this->actingAs($athlete)->delete(route('athlete.videos.destroy', MarketMedia::query()->firstOrFail()));
        $this->actingAs($athlete)->post(route('athlete.videos.store'), ['url' => 'https://www.youtube.com/watch?v=bbbbbbbbbbb'])->assertSessionHasErrors('url');
        $this->actingAs($athlete)->post(route('athlete.videos.store'), ['url' => 'https://www.youtube.com/watch?v=ddddddddddd'])->assertSessionHasNoErrors();

        expect(MarketMedia::query()->count())->toBe(3);
    });

    it('removes only its own videos', function () {
        [$athlete, $profile] = videoAthlete();
        [$stranger] = videoAthlete();
        $video = MarketMedia::query()->create(['profile_id' => $profile->getKey(), 'provider' => 'vimeo', 'external_id' => '123456789', 'url' => 'https://vimeo.com/123456789']);

        $this->actingAs($stranger)->delete(route('athlete.videos.destroy', $video))->assertNotFound();
        expect(MarketMedia::query()->count())->toBe(1);

        $this->actingAs($athlete)->delete(route('athlete.videos.destroy', $video))->assertSessionHas('success');
        expect(MarketMedia::query()->count())->toBe(0);
    });

    it('lists them in the athlete area', function () {
        [$athlete, $profile] = videoAthlete();
        MarketMedia::query()->create(['profile_id' => $profile->getKey(), 'provider' => 'vimeo', 'external_id' => '123456789', 'url' => 'https://vimeo.com/123456789']);

        $this->actingAs($athlete)->get(route('athlete.dashboard'))->assertInertia(fn ($page) => $page
            ->has('videos', 1)
            ->where('videos.0.embed_url', 'https://player.vimeo.com/video/123456789'));
    });

    it('is only for athletes with the market released', function () {
        [$athlete] = videoAthlete();
        CurrentOrganization::get()->update(['opt_in_features' => null]);

        $this->actingAs($athlete)->post(route('athlete.videos.store'), ['url' => 'https://vimeo.com/123456789'])->assertRedirect();
        expect(MarketMedia::query()->count())->toBe(0);
        $this->actingAs(panelUser(Role::Admin))->post(route('athlete.videos.store'), ['url' => 'https://vimeo.com/123456789'])->assertForbidden();
    });
});

describe('what the manager sees', function () {
    it('gets the videos on the card with the provider embed address', function () {
        [, $profile] = videoAthlete();
        MarketMedia::query()->create(['profile_id' => $profile->getKey(), 'provider' => 'youtube', 'external_id' => 'dQw4w9WgXcQ', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
        $manager = managerFor(Team::factory()->create());

        $card = $this->actingAs($manager)->get(route('manager.market.index'))->viewData('page')['props']['profiles']['data'][0];

        expect($card['videos'])->toHaveCount(1)
            ->and($card['videos'][0]['embed_url'])->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->and($card['videos'][0]['provider_label'])->toBe('YouTube');
    });

    it('does not show videos of profiles that are not published', function () {
        [, $profile] = videoAthlete(['status' => ProfileStatus::Paused]);
        MarketMedia::query()->create(['profile_id' => $profile->getKey(), 'provider' => 'vimeo', 'external_id' => '123456789', 'url' => 'https://vimeo.com/123456789']);
        $manager = managerFor(Team::factory()->create());

        $props = $this->actingAs($manager)->get(route('manager.market.index'))->viewData('page')['props'];

        expect($props['profiles']['data'])->toBe([])
            ->and(json_encode($props))->not->toContain('123456789');
    });

    it('deletes the videos together with the athlete account', function () {
        [$athlete, $profile] = videoAthlete();
        MarketMedia::query()->create(['profile_id' => $profile->getKey(), 'provider' => 'vimeo', 'external_id' => '123456789', 'url' => 'https://vimeo.com/123456789']);

        $this->actingAs($athlete)->delete(route('athlete.destroy'), ['password' => 'password']);

        expect(MarketMedia::query()->count())->toBe(0);
    });
});

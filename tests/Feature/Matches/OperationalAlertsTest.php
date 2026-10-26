<?php

use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Mail\OperationalAlertDigest;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameOfficial;
use App\Modules\Matches\Models\Official;
use App\Modules\Matches\Models\OperationalAlertSent;
use App\Modules\Matches\Models\Venue;
use App\Modules\Matches\Support\OperationalAlerts;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OperationalAlertSettings;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Mail::fake();
});

/** Jogo agendado para daqui a `$hours` horas, em edição publicada, sem estádio, escalação nem árbitro. */
function soonGame(int $hours = 5): Game
{
    ['stage' => $stage, 'entries' => [$a, $b]] = fixtureDivision(2);
    $game = Game::factory()->create(['stage_id' => $stage->id, 'division_id' => $stage->division_id, 'home_entry_id' => $a->id, 'away_entry_id' => $b->id, 'status' => GameStatus::Scheduled, 'scheduled_at' => now()->addHours($hours)]);
    publishSeason($game);

    return $game;
}

function setAlerts(array $values): void
{
    $organization = CurrentOrganization::get();
    $organization->update(['settings' => ($organization->settings ?? []) + ['operational_alerts' => $values]]);
}

function problemKinds(): array
{
    return collect((new OperationalAlerts)->scan())->flatMap(fn ($alert) => collect($alert['problems'])->pluck('kind'))->unique()->sort()->values()->all();
}

it('keeps the original defaults: 24 hours ahead and every check on', function () {
    expect(OperationalAlertSettings::all())->toBe(['hours_ahead' => 24, 'checks' => ['lineup' => true, 'officials' => true, 'venue' => true, 'conflicts' => true]]);
});

describe('the scan', function () {
    it('finds a game without lineup and venue, and asks for officials only when the organization has some', function () {
        soonGame();

        expect(problemKinds())->toBe(['lineup', 'venue']);

        Official::factory()->create(['active' => true]);
        expect(problemKinds())->toBe(['lineup', 'officials', 'venue']);
    });

    it('stays quiet when everything is in place', function () {
        $game = soonGame();
        $game->update(['venue_id' => Venue::factory()->create()->id]);
        foreach ([$game->home_entry_id, $game->away_entry_id] as $entry) {
            GameLineup::factory()->create(['game_id' => $game->id, 'entry_id' => $entry, 'slot' => LineupSlot::Starter]);
        }
        $official = Official::factory()->create(['active' => true]);
        GameOfficial::factory()->create(['game_id' => $game->id, 'official_id' => $official->id]);

        expect((new OperationalAlerts)->scan())->toBe([]);
    });

    it('only looks inside the window and at scheduled games of published seasons', function () {
        soonGame(40);
        expect((new OperationalAlerts)->scan())->toBe([]);

        setAlerts(['hours_ahead' => 48, 'checks' => ['lineup' => true, 'officials' => true, 'venue' => true, 'conflicts' => true]]);
        expect((new OperationalAlerts)->scan())->not->toBe([]);

        $game = soonGame(3);
        $game->update(['status' => GameStatus::Finished]);
        $draft = soonGame(2);
        Season::query()->whereKey($draft->division->season_id)->update(['status' => 'draft']);
        expect(collect((new OperationalAlerts)->scan())->pluck('game_id')->all())->not->toContain((string) $game->id, (string) $draft->id);
    });

    it('detects a venue conflict and a team with little rest, and can be limited to chosen checks', function () {
        $venue = Venue::factory()->create();
        $first = soonGame(5);
        $second = Game::factory()->create(['stage_id' => $first->stage_id, 'division_id' => $first->division_id, 'home_entry_id' => $first->home_entry_id, 'away_entry_id' => $first->away_entry_id, 'status' => GameStatus::Scheduled, 'scheduled_at' => now()->addHours(6)]);
        $first->update(['venue_id' => $venue->id]);
        $second->update(['venue_id' => $venue->id]);

        expect(problemKinds())->toContain('conflicts');

        setAlerts(['hours_ahead' => 24, 'checks' => ['lineup' => false, 'officials' => false, 'venue' => false, 'conflicts' => false]]);
        expect((new OperationalAlerts)->scan())->toBe([]);
    });
});

describe('the e-mail', function () {
    it('goes to who manages competitions, once per problem, and again only for new problems', function () {
        $admin = panelUser(Role::Admin);
        $organizer = panelUser(Role::Organizer);
        $operator = panelUser(Role::Operator);
        $game = soonGame();

        $this->artisan('alerts:operational')->assertSuccessful();

        Mail::assertQueued(OperationalAlertDigest::class, 2);
        Mail::assertQueued(OperationalAlertDigest::class, fn ($mail) => $mail->hasTo($admin->email) && $mail->games[0]['problems'] !== []);
        Mail::assertQueued(OperationalAlertDigest::class, fn ($mail) => $mail->hasTo($organizer->email));
        Mail::assertNotQueued(OperationalAlertDigest::class, fn ($mail) => $mail->hasTo($operator->email));

        $this->artisan('alerts:operational')->assertSuccessful();
        Mail::assertQueued(OperationalAlertDigest::class, 2);

        // Um problema novo (outra equipe sem escalação em outro jogo) volta a avisar, só com o que é novo.
        soonGame(6);
        $this->artisan('alerts:operational')->assertSuccessful();
        Mail::assertQueued(OperationalAlertDigest::class, 4);
        expect(OperationalAlertSent::query()->count())->toBeGreaterThan(3);
        expect($game->fresh())->not->toBeNull();
    });

    it('does not mark anything as warned when nobody can receive it, so a later round tries again', function () {
        soonGame();

        $this->artisan('alerts:operational')->assertSuccessful();

        Mail::assertNothingQueued();
        expect(OperationalAlertSent::query()->count())->toBe(0);
    });

    it('is turned off by the organization or by the installation', function () {
        panelUser(Role::Admin);
        soonGame();

        CurrentOrganization::get()->update(['settings' => ['communications' => ['operational_alerts' => false]]]);
        $this->artisan('alerts:operational')->assertSuccessful();
        Mail::assertNothingQueued();

        CurrentOrganization::get()->update(['settings' => []]);
        config(['platform.operational_alerts.enabled' => false]);
        $this->artisan('alerts:operational')->assertSuccessful();
        Mail::assertNothingQueued();
    });

    it('forgets old records', function () {
        $old = OperationalAlertSent::query()->create(['dedupe_key' => 'velho']);
        OperationalAlertSent::query()->whereKey($old->id)->update(['created_at' => now()->subDays(10)]);
        OperationalAlertSent::query()->create(['dedupe_key' => 'novo']);

        $this->artisan('alerts:operational')->assertSuccessful();

        expect(OperationalAlertSent::query()->pluck('dedupe_key')->all())->toBe(['novo']);
    });
});

describe('the parameters', function () {
    it('saves the window and the checks with validation and keeps them when the form does not send the block', function () {
        $payload = ['disabled' => [], 'mesario' => ['days_ahead' => 2, 'finished_hours' => 6, 'limit' => 40, 'history' => 15], 'portal' => ['home' => 8, 'widgets' => 6, 'scorers' => 10, 'stats_top' => 5, 'stats_form' => 5], 'public_api' => true, 'quick_result' => true];
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->patch(route('panel.parameters.update'), $payload + ['operational_alerts' => ['hours_ahead' => 48, 'checks' => ['lineup' => true, 'officials' => false, 'venue' => true, 'conflicts' => false]]])->assertSessionHas('success');
        expect(OperationalAlertSettings::all())->toBe(['hours_ahead' => 48, 'checks' => ['lineup' => true, 'officials' => false, 'venue' => true, 'conflicts' => false]]);

        $this->actingAs($admin)->patch(route('panel.parameters.update'), $payload + ['operational_alerts' => ['hours_ahead' => 100, 'checks' => ['lineup' => true]]])->assertSessionHasErrors('operational_alerts.hours_ahead');
        expect(OperationalAlertSettings::all()['hours_ahead'])->toBe(48);

        $this->actingAs($admin)->patch(route('panel.parameters.update'), $payload)->assertSessionHas('success');
        expect(OperationalAlertSettings::all()['hours_ahead'])->toBe(48);
    });
});

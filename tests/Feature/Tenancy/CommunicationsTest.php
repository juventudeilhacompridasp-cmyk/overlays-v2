<?php

use App\Models\User;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Jobs\SendGamePush;
use App\Modules\Registration\Mail\RegistrationMail;
use App\Modules\Registration\Models\Registration;
use App\Modules\Teams\Mail\ManagerNotice;
use App\Modules\Teams\Mail\TeamManagerInvitation;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Support\ManagerNotifier;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\CommunicationSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Mail::fake();
});

function setCommunications(array $values): void
{
    CurrentOrganization::get()->update(['settings' => ['communications' => $values]]);
}

/** Jogo daqui a `$hours` horas com um gestor no mandante. */
function commsGame(int $hours = 3): Game
{
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);

    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    $game->update(['scheduled_at' => now()->addHours($hours)]);
    managerFor($game->homeEntry->team);
    Mail::fake();

    return $game->fresh();
}

describe('the settings', function () {
    it('sends everything by default, with the reminders at 24 h and 2 h', function () {
        $all = CommunicationSettings::all();

        expect(collect(CommunicationSettings::SWITCHES)->keys()->every(fn (string $key) => $all[$key] === true))->toBeTrue()
            ->and(CommunicationSettings::reminderMinutes())->toBe([1440, 120]);
    });

    it('lets the saved value override the default and keeps the second reminder closer to the deadline', function () {
        setCommunications(['registration_opened' => false, 'reminder_first_minutes' => 30, 'reminder_second_minutes' => 90]);

        expect(CommunicationSettings::enabled('registration_opened'))->toBeFalse()
            ->and(CommunicationSettings::enabled('registration_approved'))->toBeTrue()
            ->and(CommunicationSettings::reminderMinutes())->toBe([91, 90]);
    });
});

describe('the settings screen', function () {
    function communicationsPayload(array $override = []): array
    {
        return $override + collect(CommunicationSettings::SWITCHES)->map(fn () => true)->all() + ['reminder_first_minutes' => 600, 'reminder_second_minutes' => 60];
    }

    it('is saved by the administrator and shows the saved values', function () {
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->patch(route('panel.communications.update'), communicationsPayload(['push_score' => false, 'reminder_first_minutes' => 600]))
            ->assertSessionHas('success');

        expect(CommunicationSettings::enabled('push_score', Organization::query()->firstOrFail()))->toBeFalse();

        $this->actingAs($admin)->get(route('panel.communications.edit'))->assertInertia(fn ($page) => $page
            ->component('Panel/Communications/Edit')
            ->where('settings.push_score', false)
            ->where('settings.reminder_first_minutes', 600)
            ->where('settings.reminder_second_minutes', 60));
    });

    it('rejects a second reminder that is not closer to the deadline than the first', function () {
        $this->actingAs(panelUser(Role::Admin))
            ->patch(route('panel.communications.update'), communicationsPayload(['reminder_first_minutes' => 60, 'reminder_second_minutes' => 120]))
            ->assertSessionHasErrors('reminder_second_minutes');
    });

    it('is for administrators only', function () {
        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.communications.edit'))->assertForbidden();
        $this->actingAs(panelUser(Role::Organizer))->patch(route('panel.communications.update'), communicationsPayload())->assertRedirect();

        expect(CurrentOrganization::get()->fresh()->settings['communications'] ?? null)->toBeNull();
    });
});

describe('manager notices', function () {
    it('skips a notice type the organization turned off, and does not mark it as sent', function () {
        $team = Team::factory()->create();
        $manager = managerFor($team);
        setCommunications(['manager_game_changed' => false]);

        $send = fn () => ManagerNotifier::send(CurrentOrganization::get(), $team, 'game_changed', 'k1', 'Jogo atualizado', 'Mudou', ['linha'], '/gestor');

        expect($send())->toBe(0);
        Mail::assertNotQueued(ManagerNotice::class);

        setCommunications(['manager_game_changed' => true]);

        expect($send())->toBe(1);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($manager->email));
    });

    it('keeps sending the other types when only one is off', function () {
        $team = Team::factory()->create();
        managerFor($team);
        setCommunications(['manager_suspensions' => false]);

        expect(ManagerNotifier::send(CurrentOrganization::get(), $team, 'suspensions', 'k2', 'S', 'S', [], '/gestor'))->toBe(0)
            ->and(ManagerNotifier::send(CurrentOrganization::get(), $team, 'game_changed', 'k3', 'J', 'J', [], '/gestor'))->toBe(1);
    });

    it('uses the reminder times of the organization', function () {
        commsGame(3); // prazo padrão: 30 min antes do jogo, ou seja, daqui a 150 min
        setCommunications(['reminder_first_minutes' => 60, 'reminder_second_minutes' => 20]);

        $this->artisan('managers:remind-lineups')->assertSuccessful();
        Mail::assertNothingQueued(); // ainda falta mais que o primeiro lembrete (60 min)

        $this->travelTo(now()->addMinutes(100)); // faltam 50 min para o prazo
        $this->artisan('managers:remind-lineups');

        Mail::assertQueued(ManagerNotice::class, 1);
    });

    it('sends no reminder when the organization turned them off', function () {
        commsGame(3);
        setCommunications(['manager_lineup_reminder' => false]);

        $this->artisan('managers:remind-lineups')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('creates the manager access without e-mailing the invitation when it is off', function () {
        $team = Team::factory()->create();
        setCommunications(['manager_invitation' => false]);

        $this->actingAs(panelUser(Role::Admin))->post(route('panel.teams.managers.store', $team), ['name' => 'Gestor Novo', 'email' => 'novo@exemplo.com'])->assertSessionHas('success');

        Mail::assertNotQueued(TeamManagerInvitation::class);
        expect(User::query()->where('email', 'novo@exemplo.com')->exists())->toBeTrue();
    });
});

describe('registration e-mails', function () {
    it('does not e-mail the personal link when that e-mail is off, but still creates the request', function () {
        $division = Division::factory()->create();
        $season = Season::query()->findOrFail($division->season_id);
        $season->update(['status' => SeasonStatus::Registration, 'registration' => ['min_athletes' => 2, 'max_athletes' => 4, 'require_documents' => false]]);
        setCommunications(['registration_opened' => false]);

        $this->post("/inscricoes/{$season->competition->slug}/{$season->id}", [
            'division_id' => $division->id, 'team_name' => 'Ilha FC', 'team_short_name' => 'ILH', 'team_city' => 'Ilhabela',
            'contact_name' => 'Maria', 'contact_email' => 'maria@exemplo.com', 'contact_phone' => '12999990000', 'consent' => true,
        ])->assertRedirect();

        Mail::assertNotQueued(RegistrationMail::class);
        expect(Registration::query()->count())->toBe(1);
    });
});

describe('push moments', function () {
    it('queues only the moments the organization kept on', function () {
        config(['webpush.public_key' => 'chave-publica', 'webpush.private_key' => 'chave-privada']);
        ['stage' => $stage] = fixtureDivision(2);
        app(GenerateStageFixtures::class)->handle($stage);
        $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
        publishSeason($game);
        setCommunications(['push_score' => false]);
        Queue::fake();

        $game = $game->fresh();
        $game->update(['status' => GameStatus::InProgress]);
        $game->update(['home_score' => 1]);
        $game->update(['status' => GameStatus::Finished]);

        Queue::assertPushed(SendGamePush::class, fn ($job) => $job->kind === SendGamePush::STARTED);
        Queue::assertPushed(SendGamePush::class, fn ($job) => $job->kind === SendGamePush::FINISHED);
        Queue::assertNotPushed(SendGamePush::class, fn ($job) => $job->kind === SendGamePush::SCORE);
    });
});

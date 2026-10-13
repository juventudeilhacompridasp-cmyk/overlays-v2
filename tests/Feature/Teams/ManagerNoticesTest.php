<?php

use App\Models\User;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Matches\Models\Venue;
use App\Modules\Teams\Mail\ManagerNotice;
use App\Modules\Teams\Models\TeamManager;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationUrl;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Mail::fake();
});

/**
 * Jogo agendado para daqui a `$hours` horas, com um gestor no mandante e outro no visitante.
 *
 * @return array{game: Game, home: Entry, away: Entry, homeManager: User, awayManager: User}
 */
function noticeGame(int $hours = 3): array
{
    ['stage' => $stage] = fixtureDivision(2);
    app(GenerateStageFixtures::class)->handle($stage);

    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    $game->update(['scheduled_at' => now()->addHours($hours)]);
    Mail::fake(); // a marcação do horário acima não conta

    return [
        'game' => $game->fresh(),
        'home' => $game->homeEntry,
        'away' => $game->awayEntry,
        'homeManager' => managerFor($game->homeEntry->team),
        'awayManager' => managerFor($game->awayEntry->team),
    ];
}

describe('lineup reminders', function () {
    it('reminds the managers of both teams when the deadline is within a day, once', function () {
        ['game' => $game, 'homeManager' => $homeManager, 'awayManager' => $awayManager] = noticeGame(3); // prazo padrão: 30 min antes

        $this->artisan('managers:remind-lineups')->assertSuccessful();

        Mail::assertQueued(ManagerNotice::class, 2);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($homeManager->email)
            && str_contains($mail->subjectLine, 'Escalação pendente')
            && str_ends_with($mail->link, "/gestor/jogos/{$game->id}"));
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($awayManager->email));

        $this->artisan('managers:remind-lineups')->assertSuccessful();
        Mail::assertQueued(ManagerNotice::class, 2); // nada novo
    });

    it('sends a second, more urgent reminder when the deadline gets close', function () {
        noticeGame(3);

        $this->artisan('managers:remind-lineups');
        Mail::assertQueued(ManagerNotice::class, 2);

        $this->travelTo(now()->addHours(2)); // faltam 1 h para o jogo: o prazo (30 min antes) está a 30 min
        $this->artisan('managers:remind-lineups');

        Mail::assertQueued(ManagerNotice::class, 4);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => str_contains($mail->headline, 'Falta pouco'));
    });

    it('skips the team that already confirmed the lineup', function () {
        ['game' => $game, 'home' => $home, 'homeManager' => $homeManager, 'awayManager' => $awayManager] = noticeGame(3);
        GameTactic::query()->create(['organization_id' => $game->organization_id, 'game_id' => $game->id, 'entry_id' => $home->id, 'formation' => '4-4-2', 'confirmed_at' => now()]);

        $this->artisan('managers:remind-lineups');

        Mail::assertQueued(ManagerNotice::class, 1);
        Mail::assertNotQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($homeManager->email));
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($awayManager->email));
    });

    it('does not remind after the deadline or when the game is still far away', function () {
        noticeGame(0); // jogo daqui a pouco: prazo já passou

        $this->artisan('managers:remind-lineups');
        Mail::assertNothingQueued();

        noticeGame(72); // prazo daqui a mais de 24 h
        $this->artisan('managers:remind-lineups');
        Mail::assertNothingQueued();
    });

    it('respects the opt-out of each manager', function () {
        ['homeManager' => $homeManager, 'awayManager' => $awayManager] = noticeGame(3);
        TeamManager::query()->where('user_id', $homeManager->id)->update(['notify' => false]);

        $this->artisan('managers:remind-lineups');

        Mail::assertQueued(ManagerNotice::class, 1);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($awayManager->email));
    });

    it('sends nothing to organizations without the team portal', function () {
        noticeGame(3);
        $organization = CurrentOrganization::get();
        $organization->update(['plan' => Plan::Basic->value, 'features' => null]);

        $this->artisan('managers:remind-lineups');

        Mail::assertNothingQueued();
    });

    it('uses the address of the organization in the link', function () {
        config(['app.url' => 'https://app.exemplo.com', 'platform.base_domain' => 'ligas.exemplo.com']);

        $liga = Organization::factory()->create(['slug' => 'liga-x']);
        $padrao = Organization::query()->where('slug', config('platform.default_organization_slug'))->firstOrFail();

        expect(OrganizationUrl::to($liga, '/gestor/jogos/abc'))->toBe('https://liga-x.ligas.exemplo.com/gestor/jogos/abc')
            ->and(OrganizationUrl::to($padrao, 'gestor'))->toBe('https://app.exemplo.com/gestor');
    });
});

describe('changes to the game', function () {
    it('warns both managers when the date changes', function () {
        ['game' => $game, 'homeManager' => $homeManager, 'awayManager' => $awayManager] = noticeGame(100);
        $new = now()->addDays(9)->setTime(15, 30);

        $game->update(['scheduled_at' => $new]);

        Mail::assertQueued(ManagerNotice::class, 2);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($homeManager->email)
            && str_contains($mail->subjectLine, 'Jogo atualizado')
            && in_array('Quando: '.$new->format('d/m/Y H:i'), $mail->lines, true));
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($awayManager->email));
    });

    it('warns about a new venue, a postponement and a cancellation', function () {
        ['game' => $game] = noticeGame(100);

        $game->update(['venue_id' => Venue::factory()->create(['name' => 'Estádio Novo'])->id]);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => in_array('Local: Estádio Novo', $mail->lines, true));

        $game->update(['status' => GameStatus::Postponed]);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => str_contains($mail->headline, 'Adiado'));

        $game->update(['status' => GameStatus::Cancelled]);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => str_contains($mail->headline, 'Cancelado'));
    });

    it('does not warn about changes that are not about when, where or whether', function () {
        ['game' => $game] = noticeGame(100);

        $game->update(['round' => 7]);
        $game->update(['status' => GameStatus::InProgress, 'started_at' => now()]);

        Mail::assertNothingQueued();
    });

    it('does not warn when the organization has no team portal', function () {
        ['game' => $game] = noticeGame(100);
        CurrentOrganization::get()->update(['plan' => Plan::Basic->value, 'features' => null]);

        $game->update(['scheduled_at' => now()->addDays(9)]);

        Mail::assertNothingQueued();
    });
});

describe('suspensions', function () {
    it('tells the manager which athletes are suspended after the game', function () {
        ['game' => $game, 'home' => $home, 'away' => $away, 'hp' => $hp] = sheetGame();
        $homeManager = managerFor($home->team);
        $awayManager = managerFor($away->team);

        app(RecordGameEvent::class)->handle($game, GameEventType::RedCard, $home->id, $hp[0]);
        app(FinishGame::class)->handle($game);

        Mail::assertQueued(ManagerNotice::class, 1);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($homeManager->email)
            && str_contains($mail->subjectLine, 'Suspensões')
            && collect($mail->lines)->contains(fn (string $line) => str_contains($line, '1 jogo')));
        Mail::assertNotQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($awayManager->email));
    });

    it('stays quiet when the cards do not lead to suspension', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        managerFor($home->team);

        app(RecordGameEvent::class)->handle($game, GameEventType::YellowCard, $home->id, $hp[0]);
        app(FinishGame::class)->handle($game);

        Mail::assertNothingQueued();
    });
});

describe('the opt-out', function () {
    it('lets the manager turn the e-mails off and on for all of his teams', function () {
        ['homeManager' => $manager, 'awayManager' => $other] = noticeGame(3);

        $this->actingAs($manager)->patch(route('manager.notices.update'), ['notify' => false])->assertSessionHas('success');

        expect(TeamManager::query()->where('user_id', $manager->id)->value('notify'))->toBeFalse()
            ->and(TeamManager::query()->where('user_id', $other->id)->value('notify'))->toBeTrue();

        $this->actingAs($manager)->get(route('manager.dashboard'))->assertInertia(fn ($page) => $page->where('notify', false));

        $this->actingAs($manager)->patch(route('manager.notices.update'), ['notify' => true]);
        $this->actingAs($manager)->get(route('manager.dashboard'))->assertInertia(fn ($page) => $page->where('notify', true));
    });

    it('is only for managers', function () {
        $this->actingAs(panelUser(Role::Admin))->patch(route('manager.notices.update'), ['notify' => false])->assertForbidden();
    });
});

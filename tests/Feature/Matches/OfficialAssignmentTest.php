<?php

use App\Modules\Matches\Actions\AssignGameOfficials;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\OfficialRole;
use App\Modules\Matches\Mail\OfficialAssignmentNotice;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameOfficial;
use App\Modules\Matches\Models\Official;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Mail::fake();
});

function officialsGame(): Game
{
    ['stage' => $stage] = fixtureDivision(4);
    app(GenerateStageFixtures::class)->handle($stage);

    return Game::query()->where('stage_id', $stage->id)->orderBy('number')->firstOrFail();
}

describe('officials registry', function () {
    it('is managed by who manages competitions', function () {
        $this->actingAs(panelUser(Role::Organizer))
            ->post(route('panel.officials.store'), ['name' => 'Ana Árbitra', 'email' => 'ana@example.com', 'phone' => '', 'active' => true])
            ->assertRedirect(route('panel.officials.index'));

        $official = Official::query()->firstOrFail();
        expect($official->name)->toBe('Ana Árbitra');

        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.officials.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Panel/Officials/Index')->has('officials.data', 1));
        $this->actingAs(panelUser(Role::Organizer))->patch(route('panel.officials.update', $official), ['name' => 'Ana', 'email' => '', 'phone' => '1', 'active' => false])->assertRedirect();
        expect($official->fresh()->active)->toBeFalse();
    });

    it('closes the registry to who only operates games or views', function () {
        $official = Official::factory()->create();

        $this->actingAs(panelUser(Role::Operator))->get(route('panel.officials.index'))->assertForbidden();
        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.officials.edit', $official))->assertForbidden();
        $this->actingAs(panelUser(Role::Viewer))->delete(route('panel.officials.destroy', $official))->assertRedirect();
        expect(Official::query()->count())->toBe(1);
    });

    it('validates name and e-mail', function () {
        $this->actingAs(panelUser(Role::Organizer))
            ->post(route('panel.officials.store'), ['name' => '', 'email' => 'nao-e-email', 'active' => true])
            ->assertSessionHasErrors(['name', 'email']);
    });
});

describe('assigning officials to a game', function () {
    it('saves one person per role and notifies who entered by e-mail', function () {
        $game = officialsGame();
        $referee = Official::factory()->create(['email' => 'arbitro@example.com']);
        $scorer = Official::factory()->create(['email' => null]);

        $added = app(AssignGameOfficials::class)->handle($game, ['referee' => $referee->id, 'scorer' => $scorer->id, 'fourth_official' => '']);

        expect($added)->toBe(2)
            ->and(GameOfficial::query()->where('game_id', $game->id)->count())->toBe(2);
        Mail::assertQueued(OfficialAssignmentNotice::class, 1);
        Mail::assertQueued(OfficialAssignmentNotice::class, fn (OfficialAssignmentNotice $mail) => $mail->hasTo('arbitro@example.com') && $mail->roleLabel === 'Árbitro');
        expect(GameOfficial::query()->where('official_id', $referee->id)->firstOrFail()->notified_at)->not->toBeNull()
            ->and(GameOfficial::query()->where('official_id', $scorer->id)->firstOrFail()->notified_at)->toBeNull();
    });

    it('keeps untouched roles, removes a person with an empty value and does not notify again', function () {
        $game = officialsGame();
        [$a, $b] = [Official::factory()->create(), Official::factory()->create()];
        $assign = app(AssignGameOfficials::class);
        $assign->handle($game, ['referee' => $a->id, 'scorer' => $b->id]);
        Mail::fake();

        expect($assign->handle($game, ['referee' => $a->id]))->toBe(0);
        Mail::assertNothingQueued();

        $assign->handle($game, ['scorer' => '']);

        expect(GameOfficial::query()->where('game_id', $game->id)->pluck('official_id')->all())->toBe([$a->id]);
    });

    it('lets two people swap roles in the same save', function () {
        $game = officialsGame();
        [$a, $b] = [Official::factory()->create(), Official::factory()->create()];
        $assign = app(AssignGameOfficials::class);
        $assign->handle($game, ['referee' => $a->id, 'assistant_referee_1' => $b->id]);

        $assign->handle($game, ['referee' => $b->id, 'assistant_referee_1' => $a->id]);

        expect(GameOfficial::query()->where('game_id', $game->id)->where('role', OfficialRole::Referee)->value('official_id'))->toBe($b->id)
            ->and(GameOfficial::query()->where('game_id', $game->id)->where('role', OfficialRole::AssistantReferee1)->value('official_id'))->toBe($a->id);
    });

    it('refuses a person in two roles, an unknown role, an inactive person and a finished game', function () {
        $game = officialsGame();
        $a = Official::factory()->create();
        $inactive = Official::factory()->create(['active' => false]);
        $assign = app(AssignGameOfficials::class);

        expect(fn () => $assign->handle($game, ['referee' => $a->id, 'scorer' => $a->id]))->toThrow(InvalidArgumentException::class, 'duas funções')
            ->and(fn () => $assign->handle($game, ['goalkeeper' => $a->id]))->toThrow(InvalidArgumentException::class, 'Função desconhecida')
            ->and(fn () => $assign->handle($game, ['referee' => $inactive->id]))->toThrow(InvalidArgumentException::class, 'inativo')
            ->and(fn () => $assign->handle($game, ['referee' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']))->toThrow(InvalidArgumentException::class, 'não está cadastrado');

        $game->update(['status' => GameStatus::Finished]);

        expect(fn () => $assign->handle($game, ['referee' => $a->id]))->toThrow(InvalidArgumentException::class, 'encerrado');
    });

    it('does not send e-mail when the organization turned the notice off', function () {
        CurrentOrganization::get()->update(['settings' => ['communications' => ['official_assignment' => false]]]);
        $game = officialsGame();

        app(AssignGameOfficials::class)->handle($game, ['referee' => Official::factory()->create()->id]);

        Mail::assertNothingQueued();
        expect(GameOfficial::query()->count())->toBe(1);
    });
});

describe('stage officials screen and route', function () {
    it('shows the games with assignments and conflicts', function () {
        $game = officialsGame();
        $other = Game::query()->where('stage_id', $game->stage_id)->whereKeyNot($game->id)->firstOrFail();
        $official = Official::factory()->create(['name' => 'Beto']);
        $game->update(['scheduled_at' => now()->addDays(2)->setTime(16, 0)]);
        $other->update(['scheduled_at' => now()->addDays(2)->setTime(17, 0)]);
        $assign = app(AssignGameOfficials::class);
        $assign->handle($game, ['referee' => $official->id]);
        $assign->handle($other, ['referee' => $official->id]);

        $this->actingAs(panelUser(Role::Organizer))
            ->get(route('panel.stages.officials', $game->stage_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Stages/Officials')
                ->has('roles', 5)
                ->has('officials', 1)
                ->has('games', 6)
                ->where('games', function ($games) use ($game, $official) {
                    $row = collect($games)->firstWhere('id', $game->id);

                    return $row['assignments']['referee'] === $official->id && count($row['conflicts']) === 1;
                }));
    });

    it('saves through the route, refuses repeated people and is closed to the operator', function () {
        $game = officialsGame();
        $a = Official::factory()->create();

        $this->actingAs(panelUser(Role::Organizer))
            ->put(route('panel.games.officials.update', $game), ['assignments' => ['referee' => $a->id]])
            ->assertSessionHas('success');
        expect(GameOfficial::query()->count())->toBe(1);

        $this->actingAs(panelUser(Role::Organizer))
            ->put(route('panel.games.officials.update', $game), ['assignments' => ['referee' => $a->id, 'scorer' => $a->id]])
            ->assertSessionHas('error');

        $this->actingAs(panelUser(Role::Operator))->get(route('panel.stages.officials', $game->stage_id))->assertForbidden();
        $this->actingAs(panelUser(Role::Operator))->put(route('panel.games.officials.update', $game), ['assignments' => ['referee' => '']])->assertRedirect();
        expect(GameOfficial::query()->count())->toBe(1);
    });

    it('never mixes another organization officials', function () {
        $game = officialsGame();
        $foreign = Organization::factory()->create();
        $official = Official::factory()->create(['organization_id' => $foreign->id]);

        $this->actingAs(panelUser(Role::Organizer))
            ->put(route('panel.games.officials.update', $game), ['assignments' => ['referee' => $official->id]])
            ->assertSessionHas('error');
        expect(GameOfficial::query()->count())->toBe(0);
    });
});

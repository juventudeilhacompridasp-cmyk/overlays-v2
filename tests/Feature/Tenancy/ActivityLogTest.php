<?php

use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\ActivityLog;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\ActivitySettings;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

describe('recording what users do', function () {
    it('records a write action with who, role, area, subject and only the field names', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        $operator = panelUser(Role::Operator);

        $this->actingAs($operator)->post(route('panel.games.events.store', $game), [
            'type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1, 'minute' => 12,
        ])->assertSessionHas('success');

        $entry = ActivityLog::query()->where('kind', 'request')->where('action', 'panel.games.events.store')->firstOrFail();
        expect($entry->user_id)->toBe($operator->id)
            ->and($entry->actor_name)->toBe($operator->name)
            ->and($entry->actor_role)->toBe('Operador de jogo')
            ->and($entry->area)->toBe('panel')
            ->and($entry->method)->toBe('POST')
            ->and($entry->outcome)->toBe('ok')
            ->and($entry->subject_type)->toBe('game')
            ->and($entry->game_id)->toBe($game->id)
            ->and($entry->subject_label)->toContain(' x ')
            ->and($entry->fields)->toContain('type', 'person_id', 'minute')
            ->and($entry->summary)->not->toBe('');
        // Nunca os valores digitados.
        expect(json_encode($entry->fields))->not->toContain($hp[0]);
    });

    it('records the goal itself and the score change with before and after', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        $operator = panelUser(Role::Operator);

        $this->actingAs($operator)->post(route('panel.games.events.store', $game), [
            'type' => 'goal', 'entry_id' => $home->id, 'person_id' => $hp[0], 'period' => 1, 'minute' => 12,
        ]);

        $event = ActivityLog::query()->where('action', 'game-event.created')->firstOrFail();
        expect($event->kind)->toBe('change')->and($event->summary)->toBe("Lance lançado: Gol aos 12'")->and($event->actor_name)->toBe($operator->name);

        $changes = ActivityLog::query()->where('action', 'game.updated')->get();
        $score = $changes->first(fn (ActivityLog $entry) => isset($entry->changes['home_score']));
        expect($score)->not->toBeNull()
            ->and($score->changes['home_score'])->toBe([0, 1])
            ->and($score->game_id)->toBe($game->id)
            ->and($score->actor_name)->toBe($operator->name)
            ->and($changes->contains(fn (ActivityLog $entry) => isset($entry->changes['status'])))->toBeTrue();
    });

    it('records a refused action with the refusal outcome', function () {
        $game = Game::factory()->create();

        $this->actingAs(panelUser(Role::Viewer))->post(route('panel.games.finish', $game))->assertRedirect();

        expect(ActivityLog::query()->where('action', 'panel.games.finish')->firstOrFail()->outcome)->toBe('refused');
    });

    it('does not record reads, guests or routes without a logged user', function () {
        $game = Game::factory()->create();

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.games.sheet', $game));
        $this->post(route('panel.games.finish', $game));

        expect(ActivityLog::query()->count())->toBe(0);
    });

    it('never records passwords, even by name', function () {
        $user = panelUser(Role::Admin);

        $this->actingAs($user)->post(route('profile.two-factor.begin'), ['password' => 'errada']);

        $entry = ActivityLog::query()->where('action', 'profile.two-factor.begin')->firstOrFail();
        expect($entry->area)->toBe('account')->and($entry->fields)->toBeNull();
    });

    it('keeps the actor name when the user is removed', function () {
        $operator = panelUser(Role::Operator);
        $game = Game::factory()->create();
        $this->actingAs($operator)->post(route('panel.games.finish', $game));
        $name = $operator->name;

        $operator->delete();

        $entry = ActivityLog::query()->firstOrFail();
        expect($entry->user_id)->toBeNull()->and($entry->actor_name)->toBe($name);
    });
});

describe('parameters of the installation', function () {
    it('can be turned off', function () {
        config(['platform.activity_log.enabled' => false]);
        $game = Game::factory()->create();

        $this->actingAs(panelUser(Role::Operator))->post(route('panel.games.finish', $game));

        expect(ActivityLog::query()->count())->toBe(0);
    });

    it('can skip refused attempts but keeps the successful ones', function () {
        config(['platform.activity_log.log_refused' => false]);
        $game = Game::factory()->create();

        $this->actingAs(panelUser(Role::Viewer))->post(route('panel.games.finish', $game));

        expect(ActivityLog::query()->where('kind', 'request')->count())->toBe(0);
    });

    it('keeps the original defaults and clamps the retention', function () {
        expect(ActivitySettings::enabled())->toBeTrue()
            ->and(ActivitySettings::logRefused())->toBeTrue()
            ->and(ActivitySettings::retentionDays())->toBe(365);

        config(['platform.activity_log.retention_days' => 1]);
        expect(ActivitySettings::retentionDays())->toBe(30);
        config(['platform.activity_log.retention_days' => 999999]);
        expect(ActivitySettings::retentionDays())->toBe(3650);
    });

    it('prunes only records older than the retention', function () {
        $old = ActivityLog::query()->create(['actor_name' => 'Velho', 'area' => 'panel', 'kind' => 'request', 'action' => 'x', 'summary' => 'x', 'outcome' => 'ok']);
        $old->forceFill(['created_at' => now()->subDays(366)])->save();
        $recent = ActivityLog::query()->create(['actor_name' => 'Novo', 'area' => 'panel', 'kind' => 'request', 'action' => 'y', 'summary' => 'y', 'outcome' => 'ok']);

        $this->artisan('activity:prune')->assertSuccessful();

        expect(ActivityLog::query()->pluck('id')->all())->toBe([$recent->id]);
    });
});

describe('the audit screen', function () {
    it('is for those who manage users only', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.activity-log.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Panel/ActivityLog/Index'));
        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.activity-log.index'))->assertForbidden();
        $this->actingAs(panelUser(Role::Operator))->get(route('panel.activity-log.export'))->assertForbidden();
        $this->get(route('panel.activity-log.index'))->assertRedirect('/login');
    });

    it('lists newest first, filters by person, outcome and game, and never shows another organization', function () {
        $ana = panelUser(Role::Operator);
        $bia = panelUser(Role::Operator);
        $game = Game::factory()->create();
        $row = fn (array $attributes) => ActivityLog::query()->create($attributes + ['area' => 'panel', 'kind' => 'request', 'action' => 'a', 'summary' => 'Resumo', 'outcome' => 'ok']);
        $row(['user_id' => $ana->id, 'actor_name' => $ana->name, 'game_id' => $game->id, 'subject_label' => 'Casa x Fora']);
        $row(['user_id' => $bia->id, 'actor_name' => $bia->name, 'outcome' => 'refused']);
        $other = Organization::factory()->create();
        ActivityLog::query()->withoutGlobalScopes()->create(['organization_id' => $other->id, 'actor_name' => 'Intruso', 'area' => 'panel', 'kind' => 'request', 'action' => 'z', 'summary' => 'z', 'outcome' => 'ok']);
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->get(route('panel.activity-log.index'))->assertInertia(fn ($page) => $page->has('entries.data', 2)->where('entries.data.0.actor', $bia->name));
        $this->actingAs($admin)->get(route('panel.activity-log.index', ['filter' => ['actor' => (string) $ana->id]]))->assertInertia(fn ($page) => $page->has('entries.data', 1)->where('entries.data.0.actor', $ana->name));
        $this->actingAs($admin)->get(route('panel.activity-log.index', ['filter' => ['outcome' => 'refused']]))->assertInertia(fn ($page) => $page->has('entries.data', 1)->where('entries.data.0.outcome', 'refused'));
        $this->actingAs($admin)->get(route('panel.activity-log.index', ['game' => $game->id]))->assertInertia(fn ($page) => $page->has('entries.data', 1)->where('game.id', $game->id)->where('game.label', 'Casa x Fora'));
        $this->actingAs($admin)->get(route('panel.activity-log.index', ['q' => 'intruso']))->assertInertia(fn ($page) => $page->has('entries.data', 0));
    });

    it('ignores invalid filters instead of failing', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.activity-log.index', ['game' => "x'; drop table", 'from' => 'ontem', 'sort' => 'password', 'filter' => ['area' => ['a']]]))->assertOk();
    });

    it('exports the same records as CSV without formula injection', function () {
        ActivityLog::query()->create(['actor_name' => '=HYPERLINK("x")', 'area' => 'panel', 'kind' => 'request', 'action' => 'a', 'summary' => 'Resumo', 'outcome' => 'ok']);

        $response = $this->actingAs(panelUser(Role::Admin))->get(route('panel.activity-log.export'));

        $response->assertOk();
        $csv = $response->streamedContent();
        expect($csv)->toContain('Resumo')->and($csv)->not->toContain(';=HYPERLINK')->and($csv)->toContain("'=HYPERLINK");
    });
});

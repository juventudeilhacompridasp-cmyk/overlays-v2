<?php

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayReportSettings;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Competitions\Models\Division;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function reportChannel(?Game $game = null, array $screens = [], array $settings = []): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => $game?->id, 'layers' => ['screens' => $screens], 'report_settings' => $settings]);
}

it('normalizes the report settings and keeps sections of the chosen report only', function () {
    $n = OverlayReportSettings::normalize(['kind' => 'x', 'sections' => ['timeline', 'scorers', 'overview'], 'mode' => 'x', 'seconds' => 999, 'style' => 'x', 'animation' => 'x', 'scale' => 1, 'division_id' => 'abc']);

    expect($n)->toMatchArray(['kind' => 'game', 'division_id' => null, 'sections' => ['overview', 'timeline'], 'mode' => 'manual', 'seconds' => 60, 'style' => 'broadcast', 'animation' => 'rise', 'scale' => 50])
        ->and(OverlayReportSettings::normalize(['kind' => 'championship'])['sections'])->toBe(['overview', 'standings', 'scorers']);
});

it('counts the slide on the server: manual stays, automatic moves with the clock', function () {
    $manual = OverlayReportSettings::normalize(['sections' => ['overview', 'comparison', 'timeline'], 'step' => 4]);
    $auto = OverlayReportSettings::normalize(['sections' => ['overview', 'comparison', 'timeline'], 'mode' => 'auto', 'seconds' => 10, 'step' => 1, 'anchor_ms' => 1_000]);

    expect(OverlayReportSettings::slideAt($manual, 3, 99_000))->toBe(1)
        ->and(OverlayReportSettings::slideAt($auto, 3, 1_000))->toBe(1)
        ->and(OverlayReportSettings::slideAt($auto, 3, 11_000))->toBe(2)
        ->and(OverlayReportSettings::slideAt($auto, 3, 21_000))->toBe(0);

    $next = OverlayReportSettings::command($auto, 'next', 11_000);
    expect($next['step'])->toBe(0)->and($next['anchor_ms'])->toBe(11_000)
        ->and(OverlayReportSettings::command($manual, 'previous', 0)['step'])->toBe(0)
        ->and(OverlayReportSettings::command(['step' => 0, 'sections' => ['overview', 'comparison']], 'previous', 0)['step'])->toBe(1);
});

it('stays out of the state until the screen is on and then sends the public match report', function () {
    ['game' => $game, 'away' => $away, 'ap' => $ap] = sheetGame();
    Person::query()->whereKey($ap[0])->update(['name' => 'Joana Menor Souza', 'nickname' => 'Jojo', 'birth_date' => now()->subYears(14)]);
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $away->id, $ap[0], minute: 9);
    app(RecordGameEvent::class)->handle($game, GameEventType::RedCard, $away->id, $ap[1], minute: 20);
    $channel = reportChannel($game);
    $this->actingAs(panelUser(Role::Operator));
    Event::fake([OverlayUpdated::class]);

    expect(OverlayState::for($channel)['report'])->toBeNull();

    $this->patch("/painel/broadcast/{$channel->id}", ['report_settings' => ['sections' => ['overview', 'timeline']], 'screens_live' => ['report' => true]])->assertSessionHasNoErrors();

    $report = OverlayState::for($channel->fresh())['report'];
    $json = json_encode($report, JSON_UNESCAPED_UNICODE);

    expect($report['count'])->toBe(2)->and($report['step'])->toBe(0)->and($report['anchor_ms'])->toBeGreaterThan(0)
        ->and($report['document']['private'])->toBeFalse()
        ->and($report['document']['hero']['away']['score'])->toBe(1)
        ->and(collect($report['document']['sections'])->pluck('key')->all())->toBe(['overview', 'timeline'])
        ->and($json)->toContain('Joana S.')->not->toContain('Jojo')->not->toContain('Cartão vermelho')
        ->not->toContain((string) $ap[0])->not->toContain((string) $game->id);
    Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['report'] !== null);
});

it('moves the slides with the operator commands and restarts when what is shown changes', function () {
    ['game' => $game] = sheetGame();
    $channel = reportChannel($game, ['report' => true], ['sections' => ['overview', 'comparison', 'timeline']]);
    $this->actingAs(panelUser(Role::Operator));

    $this->patch("/painel/broadcast/{$channel->id}", ['report_command' => 'next'])->assertSessionHasNoErrors();
    $this->patch("/painel/broadcast/{$channel->id}", ['report_command' => 'next'])->assertSessionHasNoErrors();
    expect($channel->fresh()->report_settings['step'])->toBe(2);

    // só o visual: a etapa fica
    $this->patch("/painel/broadcast/{$channel->id}", ['report_settings' => ['style' => 'glass', 'scale' => 120]])->assertSessionHasNoErrors();
    expect($channel->fresh()->report_settings)->toMatchArray(['step' => 2, 'style' => 'glass', 'scale' => 120]);

    // outras seções: recomeça
    $this->patch("/painel/broadcast/{$channel->id}", ['report_settings' => ['sections' => ['overview', 'periods']]])->assertSessionHasNoErrors();
    expect($channel->fresh()->report_settings['step'])->toBe(0);

    $this->patch("/painel/broadcast/{$channel->id}", ['report_command' => 'previous'])->assertSessionHasNoErrors();
    expect($channel->fresh()->report_settings['step'])->toBe(1);
    $this->patch("/painel/broadcast/{$channel->id}", ['report_command' => 'reset'])->assertSessionHasNoErrors();
    expect($channel->fresh()->report_settings['step'])->toBe(0);
});

it('shows the championship report of a chosen division or of the game on air', function () {
    ['game' => $game] = sheetGame();
    $other = Division::factory()->create(['name' => 'Divisão escolhida']);
    $channel = reportChannel($game, ['report' => true], ['kind' => 'championship', 'sections' => ['overview', 'standings']]);
    $this->actingAs(panelUser(Role::Operator));

    expect(OverlayState::for($channel)['report']['document']['kind'])->toBe('championship')
        ->and(collect(OverlayState::for($channel)['report']['document']['meta'])->firstWhere('label', 'Divisão')['value'])->toBe($game->division->name);

    $this->patch("/painel/broadcast/{$channel->id}", ['report_settings' => ['division_id' => $other->id]])->assertSessionHasNoErrors();
    expect(collect(OverlayState::for($channel->fresh())['report']['document']['meta'])->firstWhere('label', 'Divisão')['value'])->toBe('Divisão escolhida');

    // relatório da partida sem jogo no ar: nada a mostrar
    $empty = reportChannel(null, ['report' => true]);
    expect(OverlayState::for($empty)['report'])->toBeNull();
});

it('validates the commands and refuses divisions of other organizations', function () {
    ['game' => $game] = sheetGame();
    $channel = reportChannel($game);
    $foreign = Organization::factory()->create();
    $division = CurrentOrganization::using($foreign, fn () => Division::factory()->create());
    $this->actingAs(panelUser(Role::Operator));

    $this->patch("/painel/broadcast/{$channel->id}", ['report_command' => 'jump'])->assertSessionHasErrors('report_command');
    $this->patch("/painel/broadcast/{$channel->id}", ['report_settings' => ['mode' => 'sempre']])->assertSessionHasErrors('report_settings.mode');
    $this->patch("/painel/broadcast/{$channel->id}", ['report_settings' => ['sections' => ['inventada']]])->assertSessionHasErrors('report_settings.sections.0');
    $this->patch("/painel/broadcast/{$channel->id}", ['report_settings' => ['division_id' => $division->id]])->assertSessionHasErrors('report_settings.division_id');
    $this->actingAs(panelUser(Role::Viewer))->patch("/painel/broadcast/{$channel->id}", ['report_command' => 'next'])->assertSessionHas('error');
});

it('is controlled by the external API and previewed without saving', function () {
    ['game' => $game] = sheetGame();
    $channel = reportChannel($game);
    $key = OverlayChannel::newControlToken();
    $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);

    $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['screens_live' => ['report' => true], 'report_settings' => ['mode' => 'auto', 'seconds' => 8]])
        ->assertOk()->assertJsonPath('data.screens.report', true)->assertJsonPath('data.report.mode', 'auto')->assertJsonPath('data.report.seconds', 8);

    $preview = reportChannel($game);
    expect(OverlayState::for($preview->withPreview('report'))['report'])->not->toBeNull()
        ->and($preview->fresh()->screensLive()['report'])->toBeFalse();

    $this->get(route('overlay.show', ['channel' => $channel->token, 'overlay' => 'relatorio']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Overlay/Report')->where('state.report.count', 3));
});

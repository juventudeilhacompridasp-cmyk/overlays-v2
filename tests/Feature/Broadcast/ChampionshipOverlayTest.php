<?php

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayStyleModel;
use App\Modules\Broadcast\Support\ChampionshipStyle;
use App\Modules\Broadcast\Support\OverlayState;
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

function championshipChannel(?Game $game = null): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => $game?->id, 'layers' => []]);
}

it('serves each championship view to guests by token and refuses unknown views, tokens and organizations', function () {
    ['game' => $game] = sheetGame();
    $channel = championshipChannel($game);

    foreach (championshipViews() as $view) {
        $this->get("/overlay/{$channel->token}/campeonato/{$view}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Overlay/Championship')->where('token', $channel->token)->where('view', $view)->has('realtime.port'));
    }

    $this->get("/overlay/{$channel->token}/campeonato/inexistente")->assertNotFound();
    $this->get('/overlay/'.str_repeat('x', 40).'/campeonato/standings')->assertNotFound();

    CurrentOrganization::set(Organization::factory()->create(['slug' => 'outra']));
    config(['platform.default_organization_slug' => 'outra']);
    $this->get("/overlay/{$channel->token}/campeonato/standings")->assertNotFound();
});

function championshipViews(): array
{
    return ['standings', 'fixtures', 'results', 'scorers', 'discipline', 'matchcard', 'ticker', 'champion'];
}

it('has nothing to show without an edition, with a draft edition or with an unknown one', function () {
    ['game' => $game] = sheetGame();
    $channel = championshipChannel($game);

    $this->getJson("/overlay/{$channel->token}/campeonato/dados")->assertOk()->assertJsonPath('data', null)->assertHeader('Cache-Control', 'no-store, private');
    $this->getJson("/overlay/{$channel->token}/campeonato/dados?edicao={$game->division->season_id}")->assertJsonPath('data', null);
    $this->getJson("/overlay/{$channel->token}/campeonato/dados?edicao=naoexiste")->assertJsonPath('data', null);
    $this->getJson('/overlay/'.championshipChannel()->token.'/campeonato/dados')->assertJsonPath('data', null);
});

it('builds the championship from the game on air, from the edition id or from the competition slug', function () {
    ['game' => $game, 'home' => $home, 'away' => $away] = sheetGame();
    publishSeason($game);
    $season = $game->division->season;
    $channel = championshipChannel($game);

    $this->getJson("/overlay/{$channel->token}/campeonato/dados")->assertOk()
        ->assertJsonPath('data.championship.name', $season->competition->name)
        ->assertJsonPath('data.championship.season', $season->name)
        ->assertJsonPath('data.championship.champion_id', null)
        ->assertJsonCount(2, 'data.teams')
        ->assertJsonCount(1, 'data.matches')
        ->assertJsonPath('data.matches.0.status', 'scheduled')
        ->assertJsonPath('data.matches.0.home_name', $home->team->name)
        ->assertJsonPath('data.matches.0.away_name', $away->team->name)
        ->assertJsonPath('data.matches.0.home_score', null)
        ->assertJsonCount(2, 'data.standings')
        ->assertJsonPath('data.cards', null);

    $idle = championshipChannel();
    $this->getJson("/overlay/{$idle->token}/campeonato/dados?edicao={$season->id}")->assertJsonPath('data.championship.season', $season->name);
    $this->getJson("/overlay/{$idle->token}/campeonato/dados?c={$season->competition->slug}")->assertJsonPath('data.championship.season', $season->name);
    $this->getJson("/overlay/{$idle->token}/campeonato/dados?c=nada")->assertJsonPath('data', null);
});

it('reports live and finished scores, the top scorers and never leaks people or documents', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    publishSeason($game);
    Person::query()->whereKey($hp[0])->update(['name' => 'João Silva', 'nickname' => 'Joãozinho', 'birth_date' => '1990-01-01']);
    Person::query()->whereKey($hp[1])->update(['name' => 'Pedro Santos', 'nickname' => 'Pepê', 'birth_date' => now()->subYears(12)->toDateString()]);
    $game->update(['status' => 'in_progress']);

    $record = app(RecordGameEvent::class);
    $record->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[0], minute: 10);
    $record->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[0], minute: 20);
    $record->handle($game->fresh(), GameEventType::Goal, $home->id, $hp[1], minute: 30);

    $response = $this->getJson('/overlay/'.championshipChannel($game)->token.'/campeonato/dados')->assertOk()
        ->assertJsonPath('data.matches.0.status', 'live')
        ->assertJsonPath('data.matches.0.home_score', 3)
        ->assertJsonPath('data.scorers.0.name', 'Joãozinho')
        ->assertJsonPath('data.scorers.0.goals', 2)
        ->assertJsonPath('data.scorers.1.name', 'Pedro S.');

    $body = $response->getContent();
    expect($body)->not->toContain('Pedro Santos')->not->toContain('document_hash')->not->toContain($hp[0])->not->toContain($hp[1]);
});

it('only sends cards when the organization turned game cards on, with public names and no ids', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    publishSeason($game);
    Person::query()->whereKey($hp[0])->update(['name' => 'Carlos Lima', 'nickname' => 'Carlão', 'birth_date' => '1985-01-01']);
    $game->update(['status' => 'in_progress']);
    $record = app(RecordGameEvent::class);
    $record->handle($game->fresh(), GameEventType::YellowCard, $home->id, $hp[0], minute: 10);
    $record->handle($game->fresh(), GameEventType::RedCard, $home->id, $hp[0], minute: 20);
    $channel = championshipChannel($game);

    $this->getJson("/overlay/{$channel->token}/campeonato/dados")->assertJsonPath('data.cards', null);

    CurrentOrganization::get()->update(['settings' => ['portal' => ['game_cards' => true]]]);
    $response = $this->getJson("/overlay/{$channel->token}/campeonato/dados")
        ->assertJsonPath('data.cards.0.name', 'Carlão')
        ->assertJsonPath('data.cards.0.yellow', 1)
        ->assertJsonPath('data.cards.0.red', 1)
        ->assertJsonPath('data.cards.0.team_name', $home->team->name);

    expect($response->getContent())->not->toContain('Carlos Lima')->not->toContain($hp[0]);
});

it('keeps the feature gate on the championship routes', function () {
    ['game' => $game] = sheetGame();
    publishSeason($game);
    $channel = championshipChannel($game);
    CurrentOrganization::get()->update(['plan' => 'basic', 'features' => ['reports']]);

    $this->get("/overlay/{$channel->token}/campeonato/standings")->assertNotFound();
    $this->getJson("/overlay/{$channel->token}/campeonato/dados")->assertNotFound();
});

describe('style models', function () {
    it('keeps only the valid style options', function () {
        $clean = ChampionshipStyle::sanitize([
            'style' => 'neon', 'anim' => 'inventada', 'pos' => 'bc', 'font' => 'oswald', 'accent' => '00E5FF', 'bg' => 'azul', 'text' => '',
            'surface' => 9999, 'radius' => '12.4', 'scale' => 1, 'head' => false, 'dots' => 1, 'view' => 'ticker', 'c' => 'copa', 'edicao' => 'x', 'rows' => 3,
        ]);

        expect($clean)->toBe(['style' => 'neon', 'pos' => 'bc', 'font' => 'oswald', 'accent' => '#00e5ff', 'text' => '', 'surface' => 100, 'radius' => 12, 'scale' => 50, 'head' => 0, 'dots' => 1]);
        expect(ChampionshipStyle::sanitize(['font' => 'system', 'style' => ['x']]))->toBe([]);
    });

    it('saves, updates by name, imports and deletes models, only for who manages competitions', function () {
        $this->actingAs(panelUser(Role::Organizer));

        $this->post(route('panel.broadcast.models.store'), ['name' => 'Final', 'options' => ['style' => 'glass', 'anim' => 'fade', 'view' => 'ticker']])->assertSessionHas('success');
        $this->post(route('panel.broadcast.models.store'), ['name' => 'Final', 'options' => ['style' => 'neon']])->assertSessionHas('success');

        expect(OverlayStyleModel::query()->count())->toBe(1)->and(OverlayStyleModel::query()->first()->options)->toBe(['style' => 'neon']);

        $this->post(route('panel.broadcast.models.store'), ['name' => 'Vazio', 'options' => ['view' => 'ticker']])->assertSessionHasErrors('name');
        $this->post(route('panel.broadcast.models.import'), ['models' => [
            ['name' => 'Um', 'options' => ['style' => 'light']],
            ['name' => 'Ruim', 'options' => ['x' => 1]],
            ['name' => 'Final', 'options' => ['anim' => 'zoom']],
        ]])->assertSessionHas('success', '2 modelo(s) importado(s).');

        expect(OverlayStyleModel::query()->orderBy('name')->pluck('options', 'name')->all())->toBe(['Final' => ['anim' => 'zoom'], 'Um' => ['style' => 'light']]);

        $this->post(route('panel.broadcast.models.import'), ['models' => [['name' => 'Ruim', 'options' => ['x' => 1]]]])->assertSessionHas('error');

        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->has('style_models', 2)->where('style_model_limit', 30)->has('championship_seasons'));

        $this->delete(route('panel.broadcast.models.destroy', OverlayStyleModel::query()->first()))->assertSessionHas('success');
        expect(OverlayStyleModel::query()->count())->toBe(1);

        $this->actingAs(panelUser(Role::Operator));
        $this->post(route('panel.broadcast.models.store'), ['name' => 'X', 'options' => ['style' => 'neon']])->assertSessionHas('error');
        $this->delete(route('panel.broadcast.models.destroy', OverlayStyleModel::query()->first()))->assertSessionHas('error');
        expect(OverlayStyleModel::query()->count())->toBe(1);
    });

    it('limits the models per organization', function () {
        $this->actingAs(panelUser(Role::Organizer));
        foreach (range(1, OverlayStyleModel::LIMIT) as $number) {
            OverlayStyleModel::query()->create(['name' => "M{$number}", 'options' => ['style' => 'neon']]);
        }

        $this->post(route('panel.broadcast.models.store'), ['name' => 'Extra', 'options' => ['style' => 'neon']])->assertSessionHasErrors('name');
        $this->post(route('panel.broadcast.models.store'), ['name' => 'M1', 'options' => ['style' => 'glass']])->assertSessionHas('success');
    });

    it('lists only published editions for the studio', function () {
        ['game' => $game] = sheetGame();
        $this->actingAs(panelUser(Role::Viewer));

        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->has('championship_seasons', 0));

        publishSeason($game);
        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->has('championship_seasons', 1)->where('championship_seasons.0.id', $game->division->season_id));
    });
});

describe('live control', function () {
    it('starts hidden and lets the operator show and hide each championship screen, telling the OBS', function () {
        $channel = championshipChannel();
        $this->actingAs(panelUser(Role::Operator));
        Event::fake([OverlayUpdated::class]);

        expect(OverlayState::for($channel)['championship'])->toBe(array_fill_keys(OverlayChannel::CHAMPIONSHIP_VIEWS, false));

        $this->patch("/painel/broadcast/{$channel->id}", ['championship_live' => ['standings' => true, 'ticker' => true, 'inventada' => true]])->assertSessionHasNoErrors();
        $this->patch("/painel/broadcast/{$channel->id}", ['championship_live' => ['ticker' => false]])->assertSessionHasNoErrors();

        $live = $channel->fresh()->championshipLive();
        expect($live['standings'])->toBeTrue()->and($live['ticker'])->toBeFalse()->and($live)->not->toHaveKey('inventada');
        Event::assertDispatched(OverlayUpdated::class, fn (OverlayUpdated $event) => $event->state['championship']['standings'] === true);

        $this->patch("/painel/broadcast/{$channel->id}", ['championship_live' => ['standings' => 'talvez']])->assertSessionHasErrors('championship_live.standings');
        $this->get('/painel/broadcast')->assertInertia(fn ($page) => $page->where('channels.0.championship_live.standings', true));
    });

    it('keeps the other layers and scenes untouched and works through the control api', function () {
        $channel = championshipChannel();
        $channel->update(['layers' => ['lineup' => true]]);
        $key = OverlayChannel::newControlToken();
        $channel->update(['control_token_hash' => OverlayChannel::controlTokenHash($key)]);

        $this->withToken($key)->patchJson("/api/v1/overlay-channels/{$channel->id}/state", ['championship_live' => ['champion' => true]])
            ->assertOk()->assertJsonPath('data.championship.champion', true)->assertJsonPath('data.layers.lineup', true);

        expect($channel->fresh()->layers['lineup'])->toBeTrue()->and($channel->fresh()->layerStates())->toBe(['lineup' => true, 'standings' => false, 'scoreboard' => true, 'lower_third' => true]);
    });

    it('gives the page the channel state for live screens', function () {
        $channel = championshipChannel();

        $this->get("/overlay/{$channel->token}/campeonato/standings?live=1")->assertOk()
            ->assertInertia(fn ($page) => $page->where('state.championship.standings', false)->where('state.server_time_ms', fn ($value) => is_int($value)));
    });
});

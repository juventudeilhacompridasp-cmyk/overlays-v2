<?php

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayBracket;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function summaryChannel(Game $game, array $screens = ['summary' => true]): OverlayChannel
{
    return OverlayChannel::query()->create(['name' => 'Canal '.fake()->unique()->word(), 'token' => OverlayChannel::newToken(), 'game_id' => $game->id, 'layers' => ['screens' => $screens]]);
}

it('serves the summary and bracket screens', function () {
    ['game' => $game] = sheetGame();
    $channel = summaryChannel($game);

    $this->get($channel->overlayUrl('resumo'))->assertOk()->assertInertia(fn ($page) => $page->component('Overlay/Summary')->has('state.summary'));
    $this->get($channel->overlayUrl('chaveamento'))->assertOk()->assertInertia(fn ($page) => $page->component('Overlay/Bracket')->has('state.bracket'));
});

describe('summary', function () {
    it('is empty while the screen is off and names the moment of the game', function () {
        ['game' => $game] = sheetGame();
        $off = summaryChannel($game, []);

        expect(OverlayState::for($off)['summary'])->toBeNull();

        $channel = summaryChannel($game);
        expect(OverlayState::for($channel)['summary']['headline'])->toBe('RESUMO');

        $game->update(['status' => GameStatus::InProgress]);
        expect(OverlayState::for($channel->fresh())['summary']['headline'])->toBe('INTERVALO');

        $game->update(['status' => GameStatus::Finished]);
        expect(OverlayState::for($channel->fresh())['summary']['headline'])->toBe('FIM DE JOGO');
    });

    it('lists cards only when the organization released them, with public names', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
        Person::query()->whereKey($hp[1])->update(['name' => 'Carlos Lima', 'nickname' => 'Carlão', 'birth_date' => '1985-01-01']);
        $game->update(['status' => GameStatus::InProgress]);
        app(RecordGameEvent::class)->handle($game->fresh(), GameEventType::YellowCard, $home->id, $hp[1], minute: 20);
        $channel = summaryChannel($game);

        expect(OverlayState::for($channel)['summary']['cards'])->toBeNull();

        CurrentOrganization::get()->update(['settings' => ['portal' => ['game_cards' => true]]]);
        $cards = OverlayState::for($channel->fresh())['summary']['cards'];

        expect($cards)->toBe([['side' => 'home', 'kind' => 'yellow_card', 'player' => 'Carlão', 'minute' => 20]]);
    });

    it('shows the player of the match chosen by the operator, only from the lineup, and clears it with the game', function () {
        ['game' => $game, 'hp' => $hp] = sheetGame();
        Person::query()->whereKey($hp[2])->update(['name' => 'Rafael Souza', 'nickname' => 'Rafa', 'birth_date' => '1990-01-01']);
        $channel = summaryChannel($game);
        $this->actingAs(panelUser(Role::Operator));

        $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['mvp_person_id' => $hp[2]]])->assertSessionHasNoErrors();
        $mvp = OverlayState::for($channel->fresh())['summary']['mvp'];

        expect($mvp)->toMatchArray(['name' => 'Rafa', 'shirt' => 3, 'side' => 'home'])
            ->and(json_encode(OverlayState::for($channel->fresh())))->not->toContain($hp[2])
            ->and(OverlayState::for($channel->fresh())['cartela'])->toBe(['title' => 'Começamos em breve', 'subtitle' => '', 'until_ms' => null]);

        $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['mvp_person_id' => Person::factory()->create()->id]])->assertSessionHasNoErrors();
        expect(OverlayState::for($channel->fresh())['summary']['mvp'])->toBeNull();

        $this->patch("/painel/broadcast/{$channel->id}", ['screen_settings' => ['mvp_person_id' => $hp[2]]]);
        $this->patch("/painel/broadcast/{$channel->id}", ['game_id' => null]);
        expect($channel->fresh()->screen_settings['mvp'])->toBeNull();
    });
});

describe('bracket', function () {
    it('has nothing for a league without knockout stage', function () {
        ['game' => $game] = sheetGame();

        expect(OverlayBracket::for($game))->toBeNull()->and(OverlayState::for(summaryChannel($game, ['bracket' => true]))['bracket'])->toBeNull();
    });

    it('draws the rounds with scores and winners, without links or athletes', function () {
        ['stage' => $stage] = fixtureDivision(4, StageType::Knockout);
        app(GenerateStageFixtures::class)->handle($stage);
        $first = Game::query()->where('stage_id', $stage->id)->where('round', 1)->orderBy('number')->firstOrFail();
        $first->update(['status' => GameStatus::Finished, 'home_score' => 2, 'away_score' => 1]);

        $bracket = OverlayState::for(summaryChannel($first->fresh(), ['bracket' => true]))['bracket'];

        expect($bracket['title'])->toBe($stage->name)
            ->and(count($bracket['rounds']))->toBe(2)
            ->and($bracket['rounds'][0]['ties'])->toHaveCount(2)
            ->and($bracket['rounds'][0]['ties'][0]['a'])->toMatchArray(['score' => 2, 'winner' => true])
            ->and($bracket['rounds'][0]['ties'][0]['b'])->toMatchArray(['score' => 1, 'winner' => false])
            ->and($bracket['champion'])->toBeNull()
            ->and(json_encode($bracket))->not->toContain('http');
    });

    it('is shown from a league game when the division has a knockout stage', function () {
        ['game' => $game] = sheetGame();
        $knockout = Stage::factory()->for($game->division)->create(['type' => StageType::Knockout, 'sequence' => 2]);
        app(GenerateStageFixtures::class)->handle($knockout);

        expect(OverlayBracket::for($game->fresh()))->not->toBeNull()->and(OverlayBracket::for($game->fresh())['title'])->toBe($knockout->name);
    });
});

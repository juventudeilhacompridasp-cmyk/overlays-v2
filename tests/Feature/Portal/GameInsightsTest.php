<?php

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** Jogo ao vivo A x B (publicado) e um jogo anterior A 2 x 0 B, na mesma fase de pontos corridos. */
function insightsGame(): array
{
    ['stage' => $stage, 'entries' => [$a, $b]] = fixtureDivision(2);

    $past = Game::factory()->create(['stage_id' => $stage->id, 'division_id' => $stage->division_id, 'home_entry_id' => $a->id, 'away_entry_id' => $b->id, 'status' => GameStatus::Finished, 'home_score' => 2, 'away_score' => 0, 'scheduled_at' => now()->subDays(10)]);
    $game = Game::factory()->create(['stage_id' => $stage->id, 'division_id' => $stage->division_id, 'home_entry_id' => $a->id, 'away_entry_id' => $b->id, 'status' => GameStatus::InProgress, 'scheduled_at' => now()]);
    publishSeason($game);

    return ['game' => $game, 'past' => $past, 'a' => $a, 'b' => $b, 'stage' => $stage];
}

function insightsEvent(Game $game, GameEventType $type, string $entryId, ?Person $person, ?int $minute, ?Person $secondary = null, int $period = 1): GameEvent
{
    return GameEvent::factory()->create(['game_id' => $game->getKey(), 'type' => $type, 'entry_id' => $entryId, 'person_id' => $person?->getKey(), 'secondary_person_id' => $secondary?->getKey(), 'minute' => $minute, 'period' => $period]);
}

function insightsPage(Game $game)
{
    return test()->get(route('public.games.show', $game));
}

function insightsSettings(array $portal): void
{
    CurrentOrganization::get()->update(['settings' => ['portal' => $portal]]);
}

describe('the timeline', function () {
    it('lists goals and substitutions in order, with public names, and leaves cards out by default', function () {
        ['game' => $game, 'a' => $a, 'b' => $b] = insightsGame();
        $adult = Person::factory()->create(['name' => 'João Silva', 'nickname' => 'Joãozinho', 'birth_date' => '1990-01-01']);
        $minor = Person::factory()->create(['name' => 'Pedro Santos', 'nickname' => 'Pepê', 'birth_date' => now()->subYears(12)->toDateString()]);
        $out = Person::factory()->create(['name' => 'Carlos Lima', 'nickname' => null, 'birth_date' => '1988-05-05']);

        insightsEvent($game, GameEventType::Substitution, $b->id, $out, 60, $minor, 2);
        insightsEvent($game, GameEventType::YellowCard, $a->id, $adult, 30);
        insightsEvent($game, GameEventType::Goal, $a->id, $adult, 10);
        $voided = insightsEvent($game, GameEventType::Goal, $b->id, $out, 15);
        insightsEvent($game, GameEventType::Goal, $b->id, $out, 20);
        GameEvent::factory()->create(['game_id' => $game->getKey(), 'type' => GameEventType::Goal, 'entry_id' => $b->id, 'compensates_event_id' => $voided->getKey()]);

        insightsPage($game)->assertOk()->assertInertia(function ($page) {
            $timeline = $page->toArray()['props']['insights']['timeline'];

            expect(array_column($timeline, 'type'))->toBe(['goal', 'goal', 'substitution'])
                ->and($timeline[0])->toMatchArray(['side' => 'home', 'minute' => 10, 'player' => 'Joãozinho'])
                ->and($timeline[1])->toMatchArray(['side' => 'away', 'minute' => 20])
                ->and($timeline[2])->toMatchArray(['side' => 'away', 'minute' => 60, 'player' => 'Pedro S.', 'out' => 'Carlos Lima']);
        });
    });

    it('shows cards only when the organization turns them on', function () {
        ['game' => $game, 'a' => $a] = insightsGame();
        $person = Person::factory()->create(['name' => 'João Silva', 'nickname' => 'Joãozinho', 'birth_date' => '1990-01-01']);
        insightsEvent($game, GameEventType::YellowCard, $a->id, $person, 30);
        insightsEvent($game, GameEventType::RedCard, $a->id, $person, 80);

        insightsPage($game)->assertInertia(fn ($page) => $page->where('insights.timeline', []));

        insightsSettings(['game_cards' => true]);
        insightsPage($game)->assertInertia(function ($page) {
            expect(array_column($page->toArray()['props']['insights']['timeline'], 'type'))->toBe(['yellow_card', 'red_card']);
        });
    });

    it('does not exist for sports that score by sets or points', function () {
        ['game' => $game] = insightsGame();
        Competition::query()->whereKey($game->division->season->competition_id)->update(['sport' => 'volleyball']);

        insightsPage($game)->assertInertia(fn ($page) => $page->where('insights.timeline', null));
    });
});

describe('table position, form and head to head', function () {
    it('shows the position of both teams in the stage table', function () {
        ['game' => $game] = insightsGame();

        insightsPage($game)->assertInertia(fn ($page) => $page
            ->where('insights.standings.home.position', 1)
            ->where('insights.standings.home.points', 3)
            ->where('insights.standings.away.position', 2)
            ->where('insights.standings.away.of', 2));
    });

    it('has no table position for a knockout stage', function () {
        ['game' => $game, 'stage' => $stage] = insightsGame();
        $stage->update(['type' => 'knockout']);

        insightsPage($game)->assertInertia(fn ($page) => $page->where('insights.standings', null));
    });

    it('shows the last results of each team from its own point of view, before this game', function () {
        ['game' => $game, 'a' => $a, 'stage' => $stage] = insightsGame();
        $c = Entry::factory()->for($stage->division)->create(['status' => EntryStatus::Confirmed]);
        Game::factory()->create(['stage_id' => $stage->id, 'division_id' => $stage->division_id, 'home_entry_id' => $c->id, 'away_entry_id' => $a->id, 'status' => GameStatus::Finished, 'home_score' => 1, 'away_score' => 1, 'scheduled_at' => now()->subDays(5)]);
        // Depois deste jogo: não conta como "forma" dele.
        Game::factory()->create(['stage_id' => $stage->id, 'division_id' => $stage->division_id, 'home_entry_id' => $a->id, 'away_entry_id' => $c->id, 'status' => GameStatus::Finished, 'home_score' => 5, 'away_score' => 0, 'scheduled_at' => now()->addDays(3)]);

        insightsPage($game)->assertInertia(function ($page) {
            $form = $page->toArray()['props']['insights']['form'];

            expect(array_column($form['home'], 'result'))->toBe(['E', 'V'])
                ->and($form['home'][0])->toMatchArray(['score' => '1 x 1', 'home' => false])
                ->and(array_column($form['away'], 'result'))->toBe(['D'])
                ->and($form['away'][0]['score'])->toBe('0 x 2');
        });
    });

    it('summarizes the head to head between the two teams only', function () {
        ['game' => $game, 'a' => $a, 'stage' => $stage] = insightsGame();
        $c = Entry::factory()->for($stage->division)->create(['status' => EntryStatus::Confirmed]);
        Game::factory()->create(['stage_id' => $stage->id, 'division_id' => $stage->division_id, 'home_entry_id' => $c->id, 'away_entry_id' => $a->id, 'status' => GameStatus::Finished, 'home_score' => 1, 'away_score' => 1, 'scheduled_at' => now()->subDays(5)]);

        insightsPage($game)->assertInertia(function ($page) {
            $h2h = $page->toArray()['props']['insights']['head_to_head'];

            expect($h2h['summary'])->toBe(['home_wins' => 1, 'draws' => 0, 'away_wins' => 0])
                ->and($h2h['games'])->toHaveCount(1)
                ->and($h2h['games'][0])->toMatchArray(['home_score' => 2, 'away_score' => 0]);
        });
    });

    it('counts a past game with the sides swapped for the team that is home now', function () {
        ['game' => $game, 'past' => $past, 'a' => $a, 'b' => $b] = insightsGame();
        // Jogo antigo com os mandos invertidos: B 3 x 1 A.
        $past->update(['home_entry_id' => $b->id, 'away_entry_id' => $a->id, 'home_score' => 3, 'away_score' => 1]);

        insightsPage($game)->assertInertia(fn ($page) => $page->where('insights.head_to_head.summary', ['home_wins' => 0, 'draws' => 0, 'away_wins' => 1]));
    });

    it('ignores games of draft seasons, but counts the ones from other published seasons', function () {
        ['game' => $game, 'a' => $a, 'b' => $b] = insightsGame();
        $division = Division::factory()->create();
        $stage = Stage::factory()->for($division)->create();
        $other = Game::factory()->create([
            'division_id' => $division->id,
            'stage_id' => $stage->id,
            'home_entry_id' => Entry::factory()->create(['division_id' => $division->id, 'team_id' => $a->team_id])->id,
            'away_entry_id' => Entry::factory()->create(['division_id' => $division->id, 'team_id' => $b->team_id])->id,
            'status' => GameStatus::Finished,
            'home_score' => 9,
            'away_score' => 0,
            'scheduled_at' => now()->subDays(1),
        ]);

        insightsPage($game)->assertInertia(fn ($page) => $page->where('insights.head_to_head.summary.home_wins', 1));

        publishSeason($other);
        insightsPage($game)->assertInertia(fn ($page) => $page->where('insights.head_to_head.summary.home_wins', 2)->has('insights.head_to_head.games', 2));
    });

    it('is not shown at all while the season is a draft', function () {
        $draft = Game::factory()->create();

        insightsPage($draft)->assertNotFound();
    });
});

describe('the organization settings', function () {
    it('can turn each section off', function () {
        ['game' => $game] = insightsGame();
        insightsSettings(['game_timeline' => false, 'game_standings' => false, 'game_form' => false, 'game_head_to_head' => false]);

        insightsPage($game)->assertInertia(fn ($page) => $page
            ->where('insights.timeline', null)
            ->where('insights.standings', null)
            ->where('insights.form', null)
            ->where('insights.head_to_head', null));
    });

    it('is saved by the administrator on the portal screen, and by nobody else', function () {
        $payload = ['game_timeline' => true, 'game_cards' => true, 'game_standings' => false, 'game_form' => true, 'game_head_to_head' => false, 'team_history' => true, 'team_form' => true, 'team_scorers' => false, 'team_calendar' => true, 'team_roster' => true, 'season_bracket' => false, 'season_rules' => true, 'competition_page' => true, 'competition_champions' => false, 'competition_records' => true];

        $this->actingAs(panelUser(Role::Organizer))->patch(route('panel.organization.portal'), $payload);
        expect(CurrentOrganization::get()->fresh()->settings['portal'] ?? null)->toBeNull();

        $this->actingAs(panelUser(Role::Admin))->patch(route('panel.organization.portal'), $payload)->assertSessionHas('success');
        expect(CurrentOrganization::get()->fresh()->settings['portal'])->toBe($payload);

        $this->actingAs(panelUser(Role::Admin))->patch(route('panel.organization.portal'), ['game_cards' => 'talvez'])->assertSessionHasErrors();

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.organization.edit'))->assertInertia(fn ($page) => $page
            ->where('portal_settings.game_cards', true)
            ->where('portal_settings.game_standings', false)
            ->has('portal_switches', 15));
    });
});

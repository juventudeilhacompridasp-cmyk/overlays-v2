<?php

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Portal\Support\IcsCalendar;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Carbon;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** Equipe A: venceu B por 3 x 1, empatou 0 x 0 e tem um jogo agendado; a edição está publicada. */
function teamPageSetup(): array
{
    ['stage' => $stage, 'entries' => [$a, $b]] = fixtureDivision(2);
    $base = ['stage_id' => $stage->id, 'division_id' => $stage->division_id, 'home_entry_id' => $a->id, 'away_entry_id' => $b->id];

    $win = Game::factory()->create($base + ['status' => GameStatus::Finished, 'home_score' => 3, 'away_score' => 1, 'scheduled_at' => now()->subDays(20)]);
    $draw = Game::factory()->create($base + ['status' => GameStatus::Finished, 'home_score' => 0, 'away_score' => 0, 'scheduled_at' => now()->subDays(10)]);
    $next = Game::factory()->create($base + ['status' => GameStatus::Scheduled, 'scheduled_at' => now()->addDays(3)->setTime(19, 30)]);
    publishSeason($next);

    return ['a' => $a, 'b' => $b, 'win' => $win, 'draw' => $draw, 'next' => $next, 'stage' => $stage];
}

function teamPageGoal(Game $game, string $entryId, Person $person, GameEventType $type = GameEventType::Goal): GameEvent
{
    return GameEvent::factory()->create(['game_id' => $game->getKey(), 'type' => $type, 'entry_id' => $entryId, 'person_id' => $person->getKey()]);
}

function teamPageSettings(array $portal): void
{
    CurrentOrganization::get()->update(['settings' => ['portal' => $portal]]);
}

describe('campaigns, form and scorers', function () {
    it('shows the campaign of each published edition with record and table position', function () {
        ['a' => $a, 'b' => $b] = teamPageSetup();

        $this->get(route('portal.team', $a->team_id))->assertOk()->assertInertia(function ($page) {
            $campaign = $page->toArray()['props']['extras']['campaigns'][0];

            expect($campaign)->toMatchArray(['played' => 2, 'wins' => 1, 'draws' => 1, 'losses' => 0, 'for' => 3, 'against' => 1])
                ->and($campaign['position'])->toMatchArray(['position' => 1, 'of' => 2, 'points' => 4])
                ->and($campaign['url'])->toContain('/competicoes/');
        });

        $this->get(route('portal.team', $b->team_id))->assertInertia(fn ($page) => $page
            ->where('extras.campaigns.0.losses', 1)
            ->where('extras.campaigns.0.position.position', 2));
    });

    it('shows the last results from the point of view of the team, most recent first', function () {
        ['a' => $a, 'b' => $b] = teamPageSetup();

        $this->get(route('portal.team', $a->team_id))->assertInertia(function ($page) {
            $form = $page->toArray()['props']['extras']['form'];

            expect(array_column($form, 'result'))->toBe(['E', 'V'])
                ->and($form[1])->toMatchArray(['score' => '3 x 1', 'home' => true]);
        });

        $this->get(route('portal.team', $b->team_id))->assertInertia(fn ($page) => $page->where('extras.form.1.result', 'D')->where('extras.form.1.score', '1 x 3'));
    });

    it('lists the top scorers by public name, ignoring own goals and voided goals', function () {
        ['a' => $a, 'win' => $win, 'draw' => $draw] = teamPageSetup();
        $adult = Person::factory()->create(['name' => 'João Silva', 'nickname' => 'Joãozinho', 'birth_date' => '1990-01-01']);
        $minor = Person::factory()->create(['name' => 'Pedro Santos', 'nickname' => 'Pepê', 'birth_date' => now()->subYears(12)->toDateString()]);

        teamPageGoal($win, $a->id, $adult);
        teamPageGoal($win, $a->id, $adult);
        teamPageGoal($win, $a->id, $minor);
        teamPageGoal($win, $a->id, $minor, GameEventType::OwnGoal);
        $voided = teamPageGoal($draw, $a->id, $minor);
        GameEvent::factory()->create(['game_id' => $draw->getKey(), 'type' => GameEventType::Goal, 'entry_id' => $a->id, 'compensates_event_id' => $voided->getKey()]);

        $this->get(route('portal.team', $a->team_id))->assertInertia(fn ($page) => $page
            ->where('extras.scorers', [['player' => 'Joãozinho', 'goals' => 2], ['player' => 'Pedro S.', 'goals' => 1]]));
    });
});

describe('the public roster', function () {
    it('does not exist by default: the key is not even sent', function () {
        ['a' => $a] = teamPageSetup();
        RosterEntry::factory()->create(['entry_id' => $a->id, 'shirt_number' => 9]);

        $this->get(route('portal.team', $a->team_id))->assertInertia(fn ($page) => $page->missing('extras.roster')->missing('roster'));
    });

    it('lists only active athletes and the coach, with public names and no personal data, when turned on', function () {
        ['a' => $a] = teamPageSetup();
        teamPageSettings(['team_roster' => true]);
        $adult = Person::factory()->create(['name' => 'João Silva', 'nickname' => 'Joãozinho', 'birth_date' => '1990-01-01']);
        $minor = Person::factory()->create(['name' => 'Pedro Santos', 'nickname' => 'Pepê', 'birth_date' => now()->subYears(12)->toDateString()]);
        $coach = Person::factory()->create(['name' => 'Carlos Lima', 'nickname' => null, 'birth_date' => '1975-03-03']);
        $physio = Person::factory()->create(['name' => 'Rita Souza', 'birth_date' => '1980-01-01']);
        $gone = Person::factory()->create(['name' => 'Saiu Daqui', 'birth_date' => '1990-01-01']);

        RosterEntry::factory()->create(['entry_id' => $a->id, 'person_id' => $minor->id, 'shirt_number' => 7]);
        RosterEntry::factory()->create(['entry_id' => $a->id, 'person_id' => $adult->id, 'shirt_number' => 10]);
        RosterEntry::factory()->create(['entry_id' => $a->id, 'person_id' => $coach->id, 'role' => RosterRole::Coach, 'shirt_number' => null]);
        RosterEntry::factory()->create(['entry_id' => $a->id, 'person_id' => $physio->id, 'role' => RosterRole::Physio, 'shirt_number' => null]);
        RosterEntry::factory()->create(['entry_id' => $a->id, 'person_id' => $gone->id, 'status' => RosterStatus::Removed, 'shirt_number' => 11]);

        $response = $this->get(route('portal.team', $a->team_id));

        $response->assertInertia(fn ($page) => $page->where('extras.roster', [
            ['name' => 'Pedro S.', 'shirt' => 7, 'role' => 'Atleta'],
            ['name' => 'Joãozinho', 'shirt' => 10, 'role' => 'Atleta'],
            ['name' => 'Carlos Lima', 'shirt' => null, 'role' => 'Técnico'],
        ]));

        expect($response->getContent())->not->toContain('Pedro Santos')->not->toContain('Rita Souza')->not->toContain('Saiu Daqui');
    });
});

describe('the settings', function () {
    it('can turn each section off', function () {
        ['a' => $a] = teamPageSetup();
        teamPageSettings(['team_history' => false, 'team_form' => false, 'team_scorers' => false, 'team_calendar' => false]);

        $this->get(route('portal.team', $a->team_id))->assertInertia(fn ($page) => $page
            ->where('extras.campaigns', null)
            ->where('extras.form', null)
            ->where('extras.scorers', null)
            ->where('extras.calendar_url', null));

        $this->get(route('portal.team.calendar', $a->team_id))->assertNotFound();
    });
});

describe('the calendar (.ics)', function () {
    it('lists the scheduled games of the team in iCalendar format', function () {
        ['a' => $a, 'next' => $next] = teamPageSetup();

        $response = $this->get(route('portal.team.calendar', $a->team_id));

        $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $ics = $response->getContent();

        expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n")
            ->and($ics)->toContain('END:VCALENDAR')
            ->and($ics)->toContain('UID:'.$next->getKey().'@')
            ->and($ics)->toContain('DTSTART:'.$next->scheduled_at->copy()->utc()->format('Ymd\THis\Z'))
            ->and($ics)->toContain('URL:'.route('public.games.show', $next->getKey()))
            ->and(substr_count($ics, 'BEGIN:VEVENT'))->toBe(3);
    });

    it('is 404 for teams outside published seasons and leaves out cancelled games', function () {
        ['a' => $a, 'next' => $next] = teamPageSetup();
        $next->update(['status' => GameStatus::Cancelled]);

        expect(substr_count($this->get(route('portal.team.calendar', $a->team_id))->getContent(), 'BEGIN:VEVENT'))->toBe(2);

        $draft = Game::factory()->create();
        $this->get(route('portal.team.calendar', $draft->home_entry_id ? $draft->homeEntry->team_id : 'x'))->assertNotFound();
    });

    it('escapes and folds text the way the format requires', function () {
        expect(IcsCalendar::escape("A, B; C\\D\nE"))->toBe('A\, B\; C\\\\D\nE');

        $long = str_repeat('Ã', 60);
        $ics = IcsCalendar::render('Liga', [['uid' => 'x@y', 'start' => Carbon::parse('2026-10-20 19:00:00', 'UTC'), 'minutes' => 90, 'summary' => $long, 'location' => null, 'url' => 'https://exemplo.test/j', 'description' => null]], Carbon::parse('2026-10-01 00:00:00', 'UTC'));

        foreach (explode("\r\n", $ics) as $line) {
            expect(strlen($line))->toBeLessThanOrEqual(75);
        }

        expect(str_replace("\r\n ", '', $ics))->toContain('SUMMARY:'.$long)->and($ics)->toContain('DTSTART:20261020T190000Z')->toContain('DTEND:20261020T203000Z')->toContain('DTSTAMP:20261001T000000Z');
    });
});

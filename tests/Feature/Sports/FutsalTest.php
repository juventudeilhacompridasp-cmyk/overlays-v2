<?php

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Actions\VoidGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** @return array{game: Game, hp: list<string>, ap: list<string>} jogo de futsal com 5 titulares e 9 reservas por equipe */
function futsalGame(Sport $sport = Sport::Futsal): array
{
    ['stage' => $stage] = fixtureDivision(2);
    $stage->division->season->competition->update(['sport' => $sport]);
    app(GenerateStageFixtures::class)->handle($stage);

    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    $players = fn (Entry $entry): array => RosterEntry::factory()->count(14)->sequence(fn ($s) => ['shirt_number' => $s->index + 1])
        ->create(['entry_id' => $entry->id])->pluck('person_id')->all();

    $hp = $players($game->homeEntry);
    $ap = $players($game->awayEntry);
    $starters = $sport === Sport::Futsal ? 5 : 11;

    app(SaveGameLineup::class)->handle($game, $game->homeEntry, array_slice($hp, 0, $starters), array_slice($hp, $starters));
    app(SaveGameLineup::class)->handle($game, $game->awayEntry, array_slice($ap, 0, $starters), array_slice($ap, $starters));

    return ['game' => $game->fresh(), 'hp' => $hp, 'ap' => $ap];
}

describe('competition sport', function () {
    it('creates a futsal competition and defaults to football', function () {
        $this->actingAs(panelUser(Role::Organizer));

        $this->post('/painel/competitions', ['name' => 'Copa de Futsal', 'sport' => 'futsal'])->assertSessionHasNoErrors();
        $this->post('/painel/competitions', ['name' => 'Copa de Campo'])->assertSessionHasNoErrors();
        $this->post('/painel/competitions', ['name' => 'Copa Estranha', 'sport' => 'curling'])->assertSessionHasErrors('sport');

        expect(Competition::query()->where('name', 'Copa de Futsal')->value('sport'))->toBe(Sport::Futsal)
            ->and(Competition::query()->where('name', 'Copa de Campo')->value('sport'))->toBe(Sport::Football);
    });

    it('offers the sports in the form and shows the chosen one', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $competition = Competition::factory()->create(['sport' => Sport::Futsal]);

        $this->get('/painel/competitions/create')->assertInertia(fn ($page) => $page->has('sports', 2)->where('sport_locked', false));
        $this->get("/painel/competitions/{$competition->id}")->assertInertia(fn ($page) => $page->where('competition.sport_label', 'Futsal'));
    });

    it('allows changing the sport only while there are no seasons', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $competition = Competition::factory()->create(['sport' => Sport::Football]);

        $this->put("/painel/competitions/{$competition->id}", ['name' => $competition->name, 'sport' => 'futsal'])->assertSessionHasNoErrors();
        expect($competition->fresh()->sport)->toBe(Sport::Futsal);

        Season::factory()->create(['competition_id' => $competition->id]);
        $this->get("/painel/competitions/{$competition->id}/edit")->assertInertia(fn ($page) => $page->where('sport_locked', true));
        $this->put("/painel/competitions/{$competition->id}", ['name' => $competition->name, 'sport' => 'football'])->assertSessionHas('error', 'O esporte não pode mudar depois que a competição tem edições.');
        expect($competition->fresh()->sport)->toBe(Sport::Futsal);
    });

    it('starts a futsal season with the futsal regulation but keeps football seasons unchanged', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $futsal = Competition::factory()->create(['sport' => Sport::Futsal]);
        $football = Competition::factory()->create(['sport' => Sport::Football]);

        $this->get("/painel/competitions/{$futsal->id}/seasons/create")->assertInertia(fn ($page) => $page->where('rules.lineup.max_starters', 5)->where('rules.lineup.max_bench', 9));
        $this->get("/painel/competitions/{$football->id}/seasons/create")->assertInertia(fn ($page) => $page->where('rules.lineup.max_starters', 11));

        $season = Season::factory()->create(['competition_id' => $futsal->id, 'rules' => null]);
        expect($season->ruleSet()->maxStarters)->toBe(5);

        $override = Season::factory()->create(['competition_id' => $futsal->id, 'name' => 'Outra', 'rules' => ['lineup' => ['max_starters' => 6]]]);
        expect($override->ruleSet()->maxStarters)->toBe(6)->and($override->ruleSet()->maxBench)->toBe(9);
    });
});

describe('futsal game', function () {
    it('uses the 20-minute clock and 5-a-side lineups', function () {
        ['game' => $game] = futsalGame();

        expect($game->clock()->offsetSeconds(2))->toBe(1200)
            ->and($game->division->ruleSet()->maxStarters)->toBe(5);

        $this->actingAs(panelUser(Role::Operator));
        $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page
            ->where('sport', 'futsal')
            ->where('limits.starters', 5)
            ->where('limits.bench', 9)
            ->where('clock.max_period', 4)
            ->where('event_types', fn ($types) => collect($types)->pluck('value')->contains('foul')));
    });

    it('refuses a sixth starter', function () {
        ['stage' => $stage] = fixtureDivision(2);
        $stage->division->season->competition->update(['sport' => Sport::Futsal]);
        app(GenerateStageFixtures::class)->handle($stage);
        $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
        $ids = RosterEntry::factory()->count(8)->sequence(fn ($s) => ['shirt_number' => $s->index + 1])->create(['entry_id' => $game->home_entry_id])->pluck('person_id')->all();

        expect(fn () => app(SaveGameLineup::class)->handle($game, $game->homeEntry, array_slice($ids, 0, 6), array_slice($ids, 6)))->toThrow(InvalidArgumentException::class);
    });

    it('allows many rolling substitutions', function () {
        ['game' => $game, 'hp' => $hp] = futsalGame();
        $record = app(RecordGameEvent::class);

        foreach (range(0, 11) as $n) {
            $out = $n % 2 === 0 ? $hp[0] : $hp[5];
            $in = $n % 2 === 0 ? $hp[5] : $hp[0];
            $record->handle($game, GameEventType::Substitution, $game->home_entry_id, $out, $in, 1, $n + 1);
        }

        expect($game->events()->where('type', GameEventType::Substitution)->count())->toBe(12);
    });
});

describe('team fouls', function () {
    it('counts fouls per team and half in the public snapshot, with no player data', function () {
        ['game' => $game, 'hp' => $hp, 'ap' => $ap] = futsalGame();
        $record = app(RecordGameEvent::class);

        app(ChangeGamePeriod::class)->handle($game, true, 1);
        $record->handle($game, GameEventType::Foul, $game->home_entry_id, $hp[0], null, 1, 3);
        $record->handle($game, GameEventType::Foul, $game->home_entry_id, $hp[1], null, 1, 5);
        $record->handle($game, GameEventType::Foul, $game->away_entry_id, $ap[0], null, 1, 8);

        $snapshot = PublicGameSnapshot::for($game->fresh());
        expect($snapshot['sport'])->toBe('futsal')
            ->and($snapshot['fouls'])->toBe(['home' => 2, 'away' => 1, 'limit' => 5, 'period' => 1])
            ->and(json_encode($snapshot))->not->toContain($hp[0]);

        app(ChangeGamePeriod::class)->handle($game, false, 1);
        app(ChangeGamePeriod::class)->handle($game, true, 2);
        expect(PublicGameSnapshot::for($game->fresh())['fouls'])->toBe(['home' => 0, 'away' => 0, 'limit' => 5, 'period' => 2]);

        $record->handle($game, GameEventType::Foul, $game->away_entry_id, $ap[1], null, 2, 25);
        expect(PublicGameSnapshot::for($game->fresh())['fouls'])->toBe(['home' => 0, 'away' => 1, 'limit' => 5, 'period' => 2]);
    });

    it('voids a foul and broadcasts both the foul and its void', function () {
        ['game' => $game, 'hp' => $hp] = futsalGame();
        app(ChangeGamePeriod::class)->handle($game, true, 1);
        Event::fake([GameUpdated::class]);

        $foul = app(RecordGameEvent::class)->handle($game, GameEventType::Foul, $game->home_entry_id, $hp[0], null, 1, 3);
        Event::assertDispatchedTimes(GameUpdated::class, 1);

        app(VoidGameEvent::class)->handle($game, $foul);
        Event::assertDispatchedTimes(GameUpdated::class, 2);
        expect(PublicGameSnapshot::for($game->fresh())['fouls']['home'])->toBe(0);
    });

    it('requires the player to be in the lineup', function () {
        ['game' => $game] = futsalGame();
        $stranger = RosterEntry::factory()->create(['entry_id' => $game->home_entry_id])->person_id;

        expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::Foul, $game->home_entry_id, $stranger, null, 1, 3))
            ->toThrow(InvalidArgumentException::class);
    });

    it('is not recorded in football, which does not track team fouls', function () {
        ['game' => $game, 'hp' => $hp] = futsalGame(Sport::Football);

        expect(fn () => app(RecordGameEvent::class)->handle($game, GameEventType::Foul, $game->home_entry_id, $hp[0], null, 1, 3))
            ->toThrow(InvalidArgumentException::class, 'Este esporte não registra faltas');
        expect(PublicGameSnapshot::for($game->fresh())['fouls'])->toBeNull();

        $this->actingAs(panelUser(Role::Operator));
        $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page->where('event_types', fn ($types) => ! collect($types)->pluck('value')->contains('foul')));
    });

    it('records a foul from the sheet form', function () {
        ['game' => $game, 'hp' => $hp] = futsalGame();
        $this->actingAs(panelUser(Role::Operator));

        $this->post("/painel/games/{$game->id}/events", ['type' => 'foul', 'entry_id' => $game->home_entry_id, 'person_id' => $hp[0], 'period' => 1, 'minute' => 4])->assertSessionHas('success');

        expect($game->events()->where('type', GameEventType::Foul)->count())->toBe(1);
        $this->get("/painel/games/{$game->id}/sheet")->assertInertia(fn ($page) => $page->where('fouls.home', 1)->where('fouls.limit', 5));
    });
});

describe('season rules form', function () {
    it('keeps the futsal defaults when saving a season and accepts the futsal substitution limits', function () {
        $this->actingAs(panelUser(Role::Organizer));
        $competition = Competition::factory()->create(['sport' => Sport::Futsal]);
        $rules = $competition->sport->profile()->defaultRules()->toArray();

        $this->post("/painel/competitions/{$competition->id}/seasons", ['name' => 'Futsal 2030', 'year' => 2030, 'status' => 'draft', 'rules' => $rules])->assertSessionHasNoErrors();

        $season = Season::query()->where('name', 'Futsal 2030')->firstOrFail();
        expect($season->ruleSet()->maxSubstitutions)->toBe(99)
            ->and($season->ruleSet()->allowSubstitutionReentry)->toBeTrue()
            ->and($season->ruleSet()->maxStarters)->toBe(5);
    });

    it('does not let a substituted player return in football by default', function () {
        ['game' => $game, 'hp' => $hp] = futsalGame(Sport::Football);
        $record = app(RecordGameEvent::class);
        $record->handle($game, GameEventType::Substitution, $game->home_entry_id, $hp[0], $hp[11], 1, 10);

        expect(fn () => $record->handle($game, GameEventType::Substitution, $game->home_entry_id, $hp[11], $hp[0], 1, 20))
            ->toThrow(InvalidArgumentException::class, 'não pode voltar');
    });
});

<?php

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\Venue;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Support\Tables\TableQuery;
use Illuminate\Http\Request;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    $this->actingAs(panelUser());
});

/** Nomes na ordem em que a lista chegou à tela. */
function tableNames(object $page, string $key): array
{
    return collect($page->toArray()['props'][$key]['data'])->pluck('name')->all();
}

describe('TableQuery', function () {
    it('keeps only whitelisted sort, direction, page size and filters', function () {
        $request = Request::create('/x', 'GET', ['q' => '  lima  ', 'sort' => 'password; drop table', 'dir' => 'desc', 'per_page' => '9999', 'filter' => ['city' => 'Registro', 'hack' => '1', 'entries' => ['a']]]);
        $table = TableQuery::fromRequest($request, ['name', 'city'], 'name', ['city', 'entries']);

        expect($table->q)->toBe('lima')
            ->and($table->sort)->toBe('name')
            ->and($table->dir)->toBe('asc') // sem ordenação válida a direção também é a padrão
            ->and($table->perPage)->toBe(15)
            ->and($table->filters)->toBe(['city' => 'Registro']);
    });

    it('accepts a valid sort with its direction and page size', function () {
        $table = TableQuery::fromRequest(Request::create('/x', 'GET', ['sort' => 'city', 'dir' => 'DESC', 'per_page' => '50']), ['name', 'city'], 'name');

        expect($table->sort)->toBe('city')->and($table->dir)->toBe('desc')->and($table->perPage)->toBe(50);
    });
});

describe('teams list', function () {
    it('sorts by city in both directions with empty cities last', function () {
        Team::factory()->create(['name' => 'A', 'city' => 'Santos']);
        Team::factory()->create(['name' => 'B', 'city' => null]);
        Team::factory()->create(['name' => 'C', 'city' => 'Registro']);

        $this->get('/painel/teams?sort=city&dir=asc')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe(['C', 'A', 'B']));
        $this->get('/painel/teams?sort=city&dir=desc')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe(['A', 'C', 'B']));
    });

    it('sorts by the number of entries', function () {
        $busy = Team::factory()->create(['name' => 'Movimentada']);
        Team::factory()->create(['name' => 'Parada']);
        Entry::factory()->count(2)->create(['team_id' => $busy->id]);

        $this->get('/painel/teams?sort=entries&dir=desc')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe(['Movimentada', 'Parada']));
    });

    it('filters by city and by having entries', function () {
        Team::factory()->create(['name' => 'Com', 'city' => 'Registro']);
        $with = Team::factory()->create(['name' => 'Inscrita', 'city' => 'Santos']);
        Entry::factory()->create(['team_id' => $with->id]);

        $this->get('/painel/teams?filter[city]=Registro')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe(['Com']));
        $this->get('/painel/teams?filter[entries]=with')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe(['Inscrita']));
        $this->get('/painel/teams?filter[entries]=without')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe(['Com']));
    });

    it('searches every word on name, short name and city, escaping wildcards', function () {
        Team::factory()->create(['name' => 'Atlético Ilha', 'short_name' => 'ATI', 'city' => 'Cananéia']);
        Team::factory()->create(['name' => 'Real Ilha', 'city' => 'Registro']);
        Team::factory()->create(['name' => '100% Futebol']);

        $this->get('/painel/teams?q=ilha cananéia')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe(['Atlético Ilha']));
        $this->get('/painel/teams?q=ati')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toContain('Atlético Ilha'));
        $this->get('/painel/teams?q=%')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe(['100% Futebol']));
        $this->get('/painel/teams?q=_')->assertInertia(fn ($page) => expect(tableNames($page, 'teams'))->toBe([]));
    });

    it('paginates by the chosen page size and reports the state back', function () {
        Team::factory()->count(12)->create();

        $this->get('/painel/teams?per_page=10&page=2&sort=name&dir=desc&q=&filter[city]=')->assertInertia(fn ($page) => $page
            ->has('teams.data', 2)->where('teams.current_page', 2)->where('teams.last_page', 2)->where('teams.total', 12)
            ->where('filters.per_page', 10)->where('filters.sort', 'name')->where('filters.dir', 'desc'));
    });

    it('ignores an unknown sort instead of failing', function () {
        Team::factory()->create();

        $this->get('/painel/teams?sort=id%3B%20drop%20table%20teams&dir=sideways')->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.sort', 'name')->where('filters.dir', 'asc')->has('teams.data', 1));
    });

    it('lists the cities for the filter and never shows another organization', function () {
        Team::factory()->create(['city' => 'Registro']);
        Team::factory()->create(['city' => 'Registro']);
        $foreign = Organization::factory()->create();
        CurrentOrganization::using($foreign, fn () => Team::factory()->create(['name' => 'Estrangeira', 'city' => 'Outra Cidade']));

        $this->get('/painel/teams')->assertInertia(fn ($page) => $page->has('teams.data', 2)->where('city_options', ['Registro']));
    });
});

describe('people list', function () {
    it('sorts by age (youngest first) with unknown birth dates last', function () {
        Person::factory()->create(['name' => 'Velho', 'birth_date' => '1980-01-01']);
        Person::factory()->create(['name' => 'Sem data', 'birth_date' => null]);
        Person::factory()->create(['name' => 'Novo', 'birth_date' => now()->subYears(12)->format('Y-m-d')]);

        $this->get('/painel/people?sort=age&dir=asc')->assertInertia(fn ($page) => expect(tableNames($page, 'people'))->toBe(['Novo', 'Velho', 'Sem data']));
        $this->get('/painel/people?sort=age&dir=desc')->assertInertia(fn ($page) => expect(tableNames($page, 'people'))->toBe(['Velho', 'Novo', 'Sem data']));
    });

    it('filters by age group, roster and document', function () {
        $minor = Person::factory()->create(['name' => 'Menor', 'birth_date' => now()->subYears(15)->format('Y-m-d')]);
        Person::factory()->create(['name' => 'Adulto', 'birth_date' => now()->subYears(30)->format('Y-m-d'), 'document_hash' => str_repeat('a', 64)]);
        Person::factory()->create(['name' => 'Sem nascimento', 'birth_date' => null]);
        RosterEntry::factory()->create(['person_id' => $minor->id]);

        $this->get('/painel/people?filter[age]=minor')->assertInertia(fn ($page) => expect(tableNames($page, 'people'))->toBe(['Menor']));
        $this->get('/painel/people?filter[age]=adult')->assertInertia(fn ($page) => expect(tableNames($page, 'people'))->toBe(['Adulto']));
        $this->get('/painel/people?filter[age]=unknown')->assertInertia(fn ($page) => expect(tableNames($page, 'people'))->toBe(['Sem nascimento']));
        $this->get('/painel/people?filter[rosters]=with')->assertInertia(fn ($page) => expect(tableNames($page, 'people'))->toBe(['Menor']));
        $this->get('/painel/people?filter[document]=informed')->assertInertia(fn ($page) => expect(tableNames($page, 'people'))->toBe(['Adulto']));
    });

    it('never sends the document hash to the browser', function () {
        Person::factory()->create(['document_hash' => str_repeat('b', 64)]);

        $this->get('/painel/people')->assertOk()->assertDontSee(str_repeat('b', 64), false);
    });
});

describe('venues list', function () {
    it('sorts, filters and searches', function () {
        $busy = Venue::factory()->create(['name' => 'Municipal', 'city' => 'Registro', 'address' => 'Rua A']);
        Venue::factory()->create(['name' => 'Arena Sul', 'city' => 'Santos', 'address' => null]);
        Game::factory()->create(['venue_id' => $busy->id]);

        $this->get('/painel/venues?sort=name&dir=asc')->assertInertia(fn ($page) => expect(tableNames($page, 'venues'))->toBe(['Arena Sul', 'Municipal']));
        $this->get('/painel/venues?sort=games&dir=desc')->assertInertia(fn ($page) => expect(tableNames($page, 'venues'))->toBe(['Municipal', 'Arena Sul']));
        $this->get('/painel/venues?filter[games]=without')->assertInertia(fn ($page) => expect(tableNames($page, 'venues'))->toBe(['Arena Sul']));
        $this->get('/painel/venues?filter[city]=Registro')->assertInertia(fn ($page) => expect(tableNames($page, 'venues'))->toBe(['Municipal']));
        $this->get('/painel/venues?q=rua')->assertInertia(fn ($page) => expect(tableNames($page, 'venues'))->toBe(['Municipal']));
    });
});

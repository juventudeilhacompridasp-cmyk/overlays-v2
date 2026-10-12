<?php

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Teams\Actions\IssueAthleteCard;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** @return array{season: Season, entry: Entry} */
function cardSeason(SeasonStatus $status = SeasonStatus::Active): array
{
    $division = Division::factory()->create();
    $season = Season::query()->findOrFail($division->season_id);
    $season->update(['status' => $status]);
    $entry = Entry::factory()->create(['division_id' => $division->id, 'status' => EntryStatus::Confirmed]);

    return ['season' => $season->fresh(), 'entry' => $entry];
}

function rosterMember(Entry $entry, array $person = [], array $roster = []): RosterEntry
{
    return RosterEntry::factory()->create($roster + ['entry_id' => $entry->id, 'person_id' => Person::factory()->create($person)->id, 'role' => RosterRole::Athlete, 'status' => RosterStatus::Active]);
}

describe('card code', function () {
    it('is issued once, kept on repeat and renewed on demand', function () {
        ['entry' => $entry] = cardSeason();
        $member = rosterMember($entry);
        $issue = app(IssueAthleteCard::class);

        $code = $issue->handle($member);

        expect($code)->toMatch('/^[A-HJ-KM-NP-Z2-9]{10}$/')
            ->and($issue->handle($member->fresh()))->toBe($code)
            ->and($issue->handle($member->fresh(), regenerate: true))->not->toBe($code);
    });
});

describe('public verification', function () {
    it('shows the public name, team and validity — never photo, CPF or birth date', function () {
        ['entry' => $entry, 'season' => $season] = cardSeason();
        $member = rosterMember($entry, ['name' => 'Rafael Souza Lima', 'nickname' => 'Rafa', 'birth_date' => now()->subYears(25), 'document_hash' => 'segredo-hash', 'photo_path' => 'people/foto'], ['shirt_number' => 9]);
        $code = app(IssueAthleteCard::class)->handle($member);

        $response = $this->get("/carteirinha/{$code}")->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Card')
                ->where('card.valid', true)
                ->where('card.name', 'Rafa')
                ->where('card.shirt_number', 9)
                ->where('card.team', $entry->team->name)
                ->where('card.season', $season->name));

        expect($response->headers->get('Cache-Control'))->toContain('no-store');

        $json = json_encode($response->original->getData()['page']['props']);
        expect($json)->not->toContain('segredo-hash')->not->toContain('people\\/foto')->not->toContain('birth')->not->toContain('Rafael Souza');
    });

    it('abbreviates minors and accepts the code in lower case', function () {
        ['entry' => $entry] = cardSeason();
        $member = rosterMember($entry, ['name' => 'Pedro Alves Costa', 'nickname' => 'Pedrinho', 'birth_date' => now()->subYears(14)]);
        $code = app(IssueAthleteCard::class)->handle($member);

        $this->get('/carteirinha/'.strtolower($code))->assertInertia(fn ($page) => $page->where('card.name', 'Pedro C.'));
    });

    it('is invalid when the person left the roster or the entry is not confirmed', function () {
        ['entry' => $entry] = cardSeason();
        $member = rosterMember($entry);
        $code = app(IssueAthleteCard::class)->handle($member);

        $member->update(['status' => RosterStatus::Removed]);
        $this->get("/carteirinha/{$code}")->assertInertia(fn ($page) => $page->where('card.valid', false)->where('card.status_label', 'Carteirinha sem validade'));

        $member->update(['status' => RosterStatus::Active]);
        $entry->update(['status' => EntryStatus::Withdrawn]);
        $this->get("/carteirinha/{$code}")->assertInertia(fn ($page) => $page->where('card.valid', false));
    });

    it('returns 404 for unknown codes and for draft seasons', function () {
        ['entry' => $entry, 'season' => $season] = cardSeason();
        $code = app(IssueAthleteCard::class)->handle(rosterMember($entry));

        $this->get('/carteirinha/NAOEXISTE1')->assertNotFound();

        $season->update(['status' => SeasonStatus::Draft]);
        $this->get("/carteirinha/{$code}")->assertNotFound();
    });

    it('stays out of the search engines', function () {
        $this->get('/robots.txt')->assertSee('Disallow: /carteirinha', false);
    });
});

describe('panel cards', function () {
    it('renders the card of a member with a signed photo url and issues the code', function () {
        ['entry' => $entry] = cardSeason();
        $member = rosterMember($entry, ['name' => 'Rafael Souza', 'birth_date' => '1995-05-05'], ['shirt_number' => 10]);
        $this->actingAs(panelUser(Role::Organizer));

        $this->get("/painel/roster/{$member->id}/card")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Panel/Cards/Show')
            ->has('cards', 1)
            ->where('cards.0.name', 'Rafael Souza')
            ->where('cards.0.birth_date', '05/05/1995')
            ->where('cards.0.verify_url', fn ($url) => str_ends_with($url, '/carteirinha/'.$member->fresh()->card_code)));

        expect($member->fresh()->card_code)->not->toBeNull();
    });

    it('renders all active cards of the entry, athletes first by shirt, and skips removed ones', function () {
        ['entry' => $entry] = cardSeason();
        rosterMember($entry, ['name' => 'Técnico Z'], ['role' => RosterRole::Coach, 'shirt_number' => null]);
        rosterMember($entry, ['name' => 'Atleta Onze'], ['shirt_number' => 11]);
        rosterMember($entry, ['name' => 'Atleta Dois'], ['shirt_number' => 2]);
        rosterMember($entry, ['name' => 'Saiu'], ['status' => RosterStatus::Removed]);
        $this->actingAs(panelUser(Role::Organizer));

        $this->get("/painel/entries/{$entry->id}/cards")->assertOk()->assertInertia(fn ($page) => $page
            ->has('cards', 3)
            ->where('cards.0.name', 'Atleta Dois')
            ->where('cards.1.name', 'Atleta Onze')
            ->where('cards.2.name', 'Técnico Z'));
    });

    it('refuses the card of a removed member', function () {
        ['entry' => $entry] = cardSeason();
        $member = rosterMember($entry, [], ['status' => RosterStatus::Removed]);
        $this->actingAs(panelUser(Role::Organizer));

        $this->get("/painel/roster/{$member->id}/card")->assertNotFound();
    });

    it('renews the code, invalidating the old QR', function () {
        ['entry' => $entry] = cardSeason();
        $member = rosterMember($entry);
        $old = app(IssueAthleteCard::class)->handle($member);
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/roster/{$member->id}/card/regenerate")->assertSessionHas('success');

        expect($member->fresh()->card_code)->not->toBe($old);
        auth()->logout();
        $this->get("/carteirinha/{$old}")->assertNotFound();
    });

    it('keeps cards for roles that cannot manage competitions', function (Role $role) {
        ['entry' => $entry] = cardSeason();
        $member = rosterMember($entry);
        $this->actingAs(panelUser($role));

        $this->get("/painel/entries/{$entry->id}/cards")->assertForbidden();
        $this->get("/painel/roster/{$member->id}/card")->assertForbidden();
        $this->post("/painel/roster/{$member->id}/card/regenerate")->assertSessionHas('error');
    })->with([Role::Operator, Role::Viewer]);
});

describe('reports', function () {
    it('lists the pending data and the approved rosters of the season, without the CPF', function () {
        ['entry' => $entry, 'season' => $season] = cardSeason();
        rosterMember($entry, ['name' => 'Completo', 'birth_date' => '1990-01-01', 'document_hash' => 'h-completo', 'photo_path' => 'p'], ['shirt_number' => 1]);
        rosterMember($entry, ['name' => 'Sem Tudo', 'birth_date' => null, 'document_hash' => null, 'photo_path' => null], ['shirt_number' => 2]);
        rosterMember($entry, ['name' => 'Saiu', 'birth_date' => null], ['status' => RosterStatus::Removed]);
        $other = cardSeason()['entry'];
        rosterMember($other, ['name' => 'De Outra Edição', 'birth_date' => null]);
        $this->actingAs(panelUser(Role::Organizer));

        $response = $this->get("/painel/reports?season={$season->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Panel/Reports/Index')
            ->where('rosters.total', 2)
            ->where('pending.total', 1)
            ->where('pending.rows.0.Nome', 'Sem Tudo')
            ->where('pending.rows.0.Pendências', 'Data de nascimento, CPF, Foto'));

        expect(json_encode($response->original->getData()['page']['props']))->not->toContain('h-completo');
    });

    it('exports CSV with BOM, semicolons and protection against formulas', function () {
        ['entry' => $entry, 'season' => $season] = cardSeason();
        rosterMember($entry, ['name' => '=HYPERLINK("http://x")', 'birth_date' => '1990-01-01', 'document_hash' => 'h', 'photo_path' => 'p'], ['shirt_number' => 7]);
        $this->actingAs(panelUser(Role::Organizer));

        $response = $this->get("/painel/reports/{$season->id}/elencos.csv")->assertOk();
        $csv = $response->streamedContent();

        expect($response->headers->get('Content-Type'))->toContain('text/csv')
            ->and($response->headers->get('Content-Disposition'))->toContain('elencos-')
            ->and(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
            ->and($csv)->toContain('Divisão;Equipe;Camisa;Nome;Apelido;Função;Nascimento;"CPF informado";Foto')
            ->and($csv)->toContain("'=HYPERLINK")
            ->and($csv)->not->toContain(';=HYPERLINK');

        $this->get("/painel/reports/{$season->id}/pendencias.csv")->assertOk();
        $this->get("/painel/reports/{$season->id}/outro.csv")->assertNotFound();
    });

    it('is restricted to roles that manage competitions', function (Role $role) {
        ['season' => $season] = cardSeason();
        $this->actingAs(panelUser($role));

        $this->get('/painel/reports')->assertForbidden();
        $this->get("/painel/reports/{$season->id}/elencos.csv")->assertForbidden();
    })->with([Role::Operator, Role::Viewer]);
});

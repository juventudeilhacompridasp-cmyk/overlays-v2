<?php

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Support\MarketCard;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['market']]));
});

/** Atleta com perfil publicado (ou outra situação) na organização atual. */
function publishedAthlete(array $profile = [], array $user = []): MarketProfile
{
    $athlete = panelUser(Role::Athlete);
    $athlete->forceFill(['name' => 'Fulano da Silva', ...$user])->save();

    return MarketProfile::query()->create([
        'user_id' => $athlete->getKey(),
        'status' => ProfileStatus::Published,
        'birth_date' => now()->subYears(27)->toDateString(),
        'sport' => 'football',
        'positions' => ['goalkeeper'],
        'city' => 'Ilha Comprida',
        'contact_channel' => 'email',
        'contact_value' => 'segredo@example.com',
        'published_at' => now(),
        'expires_at' => now()->addDays(30),
        ...$profile,
    ]);
}

/** Nomes devolvidos pela busca do gestor com os filtros informados. */
function marketSearch(array $query): array
{
    return marketNames(test()->actingAs(test()->manager)->get(route('manager.market.index', $query)));
}

function marketNames($response): array
{
    return collect($response->viewData('page')['props']['profiles']['data'])->pluck('name')->all();
}

describe('access', function () {
    it('is for managers with the market released', function () {
        $manager = managerFor(Team::factory()->create());

        $this->actingAs($manager)->get(route('manager.market.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Manager/Market/Index'));

        CurrentOrganization::get()->update(['opt_in_features' => null]);
        $this->actingAs($manager)->get(route('manager.market.index'))->assertForbidden();
    });

    it('keeps the panel team and the athlete out', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('manager.market.index'))->assertForbidden();
        $this->actingAs(panelUser(Role::Athlete))->get(route('manager.market.index'))->assertForbidden();
    });
});

describe('what the manager sees', function () {
    it('shows only published profiles inside the revalidation deadline', function () {
        publishedAthlete(['display_name' => 'Paredão']);
        publishedAthlete(['status' => ProfileStatus::Draft, 'display_name' => 'Rascunho']);
        publishedAthlete(['status' => ProfileStatus::Paused, 'display_name' => 'Pausado']);
        publishedAthlete(['status' => ProfileStatus::Signed, 'display_name' => 'Contratado']);
        publishedAthlete(['status' => ProfileStatus::Suspended, 'display_name' => 'Suspenso']);
        publishedAthlete(['expires_at' => now()->subDay(), 'display_name' => 'Vencido']);

        $response = $this->actingAs(managerFor(Team::factory()->create()))->get(route('manager.market.index'));

        expect(marketNames($response))->toBe(['Paredão']);
    });

    it('uses the nickname or the name and never leaks contact, birth date or documents', function () {
        publishedAthlete(['display_name' => null, 'bio' => 'Goleiro.']);

        $response = $this->actingAs(managerFor(Team::factory()->create()))->get(route('manager.market.index'));
        $card = $response->viewData('page')['props']['profiles']['data'][0];

        expect($card['name'])->toBe('Fulano da Silva')
            ->and($card['age_range'])->toBe('25 a 29 anos')
            ->and($card['positions'])->toBe(['Goleiro'])
            ->and($card['sport'])->toBe('Futebol');

        $raw = json_encode($response->viewData('page')['props']);
        expect($raw)->not->toContain('segredo@example.com')
            ->and($raw)->not->toContain('contact_value')
            ->and($raw)->not->toContain('birth_date');
    });

    it('does not show profiles of another organization', function () {
        publishedAthlete(['display_name' => 'Daqui']);
        $other = Organization::factory()->create(['opt_in_features' => ['market']]);
        CurrentOrganization::using($other, fn () => publishedAthlete(['display_name' => 'De fora']));

        $response = $this->actingAs(managerFor(Team::factory()->create()))->get(route('manager.market.index'));

        expect(marketNames($response))->toBe(['Daqui']);
    });

    it('buckets the age in ranges of five years', function () {
        expect(MarketCard::ageRange(18))->toBe('18 a 19 anos')
            ->and(MarketCard::ageRange(25))->toBe('25 a 29 anos')
            ->and(MarketCard::ageRange(44))->toBe('40 a 44 anos');
    });
});

describe('filters', function () {
    beforeEach(function () {
        publishedAthlete(['display_name' => 'Goleiro SP', 'city' => 'Ilha Comprida', 'neighborhood' => 'Centro', 'level' => 'amateur', 'availability' => ['sat' => ['morning']]]);
        publishedAthlete(['display_name' => 'Atacante', 'positions' => ['forward'], 'city' => 'Registro', 'level' => 'semi_pro', 'availability' => ['wed' => ['evening']], 'birth_date' => now()->subYears(35)->toDateString()]);
        publishedAthlete(['display_name' => 'Ala Futsal', 'sport' => 'futsal', 'positions' => ['ala'], 'city' => 'Cananéia']);
        $this->manager = managerFor(Team::factory()->create());
    });

    it('filters by sport and position', function () {
        expect(marketSearch(['sport' => 'futsal']))->toBe(['Ala Futsal'])
            ->and(marketSearch(['sport' => 'football', 'position' => 'forward']))->toBe(['Atacante'])
            ->and(marketSearch(['position' => 'goalkeeper']))->toBe(['Goleiro SP']);
    });

    it('filters by city and neighborhood ignoring case', function () {
        expect(marketSearch(['city' => 'registro']))->toBe(['Atacante'])
            ->and(marketSearch(['neighborhood' => 'CENTRO']))->toBe(['Goleiro SP']);
    });

    it('filters by level, availability and age', function () {
        expect(marketSearch(['level' => 'semi_pro']))->toBe(['Atacante'])
            ->and(marketSearch(['day' => 'sat']))->toBe(['Goleiro SP'])
            ->and(marketSearch(['period' => 'evening']))->toBe(['Atacante'])
            ->and(marketSearch(['day' => 'wed', 'period' => 'evening']))->toBe(['Atacante'])
            ->and(marketSearch(['age_min' => 30]))->toBe(['Atacante'])
            ->and(marketSearch(['age_max' => 30]))->not->toContain('Atacante');
    });

    it('ignores unknown filter values instead of failing', function () {
        expect(marketSearch(['sport' => 'curling', 'day' => 'xyz', 'age_min' => 'abc']))->toHaveCount(3);
    });
});

describe('help', function () {
    it('gives the manager the market article only while the feature is on', function () {
        $manager = managerFor(Team::factory()->create());

        $this->actingAs($manager)->get(route('manager.help.show', 'mercado-de-jogadores'))->assertOk();

        CurrentOrganization::get()->update(['opt_in_features' => null]);
        $this->actingAs($manager)->get(route('manager.help.show', 'mercado-de-jogadores'))->assertNotFound();
    });
});

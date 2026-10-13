<?php

use App\Models\User;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Registration\Mail\RegistrationMail;
use App\Modules\Registration\Models\Registration;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Models\OrganizationDomain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['platform.base_domain' => 'plataforma.test']);
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function otherOrganization(array $attributes = []): Organization
{
    return Organization::factory()->create($attributes + ['name' => 'Liga Vizinha', 'slug' => 'vizinha', 'plan' => 'pro']);
}

function platformOperator(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

describe('organization by address', function () {
    it('uses the default organization for any other host', function () {
        $this->get('http://localhost/')->assertOk();
        expect(CurrentOrganization::get()->slug)->toBe(config('platform.default_organization_slug'));

        $this->get('http://plataforma.test/')->assertOk();
        expect(CurrentOrganization::get()->slug)->toBe(config('platform.default_organization_slug'));
    });

    it('opens the organization of the subdomain and isolates its data', function () {
        $vizinha = otherOrganization();
        CurrentOrganization::using($vizinha, fn () => Competition::factory()->create(['name' => 'Copa Vizinha']));

        $this->get('http://vizinha.plataforma.test/')->assertOk()->assertInertia(fn ($page) => $page->where('theme.name', 'Liga Vizinha'));
        expect(CurrentOrganization::get()->slug)->toBe('vizinha');

        // a organização padrão não enxerga a competição da vizinha
        $this->get('http://localhost/')->assertOk();
        expect(Competition::query()->count())->toBe(0);
    });

    it('answers 404 for unknown or reserved subdomains instead of falling back', function () {
        $this->get('http://naoexiste.plataforma.test/')->assertNotFound();
        $this->get('http://www.plataforma.test/')->assertNotFound();
        $this->get('http://a.b.plataforma.test/')->assertNotFound();
    });

    it('ignores subdomains when no base domain is configured', function () {
        config(['platform.base_domain' => null]);
        otherOrganization();

        $this->get('http://vizinha.plataforma.test/')->assertOk();
        expect(CurrentOrganization::get()->slug)->toBe(config('platform.default_organization_slug'));
    });

    it('serves a custom domain only while the feature is on', function () {
        $vizinha = otherOrganization();
        OrganizationDomain::query()->create(['organization_id' => $vizinha->id, 'domain' => 'ligavizinha.com.br']);

        $this->get('http://ligavizinha.com.br/')->assertOk();
        expect(CurrentOrganization::get()->slug)->toBe('vizinha');

        $vizinha->update(['plan' => 'basic']);
        $this->get('http://ligavizinha.com.br/')->assertNotFound();
    });

    it('builds public links with the host that was accessed and keeps them in the e-mail', function () {
        Mail::fake();
        $vizinha = otherOrganization();
        CurrentOrganization::using($vizinha, function () {
            $division = Division::factory()->create();
            Season::query()->findOrFail($division->season_id)->update(['status' => 'registration', 'registration' => ['min_athletes' => 1]]);
        });
        $season = CurrentOrganization::using($vizinha, fn () => Season::query()->firstOrFail());
        $competition = CurrentOrganization::using($vizinha, fn () => $season->competition);

        $division = CurrentOrganization::using($vizinha, fn () => $season->divisions()->firstOrFail());
        $this->post("http://vizinha.plataforma.test/inscricoes/{$competition->slug}/{$season->id}", [
            'division_id' => $division->id, 'team_name' => 'Time X', 'contact_name' => 'Ana', 'contact_email' => 'ana@exemplo.com', 'consent' => true,
        ]);

        Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail) => str_starts_with($mail->link, 'http://vizinha.plataforma.test/minha-inscricao/'));
        expect(Registration::withoutGlobalScopes()->count())->toBe(1);
    });
});

describe('onboarding', function () {
    it('creates the organization, its first administrator and a temporary password', function () {
        $this->actingAs(platformOperator());

        $response = $this->post('/plataforma/organizations', ['name' => 'Liga das Ilhas', 'slug' => 'ilhas', 'plan' => 'standard', 'admin_name' => 'Maria Admin', 'admin_email' => 'maria@exemplo.com']);

        $organization = Organization::query()->where('slug', 'ilhas')->firstOrFail();
        $response->assertRedirect("/plataforma/organizations/{$organization->id}")->assertSessionHas('success', fn ($message) => str_contains($message, 'Senha provisória'));

        $user = User::query()->where('email', 'maria@exemplo.com')->firstOrFail();
        expect($organization->plan()->value)->toBe('standard')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(Membership::withoutGlobalScopes()->where('organization_id', $organization->id)->where('user_id', $user->id)->value('role'))->toBe(Role::Admin);
    });

    it('reuses an existing user without touching the password', function () {
        $existing = User::factory()->create(['email' => 'maria@exemplo.com']);
        $hash = $existing->password;
        $this->actingAs(platformOperator());

        $this->post('/plataforma/organizations', ['name' => 'Liga das Ilhas', 'slug' => 'ilhas', 'plan' => 'basic', 'admin_name' => 'Maria', 'admin_email' => 'maria@exemplo.com'])
            ->assertSessionHas('success', fn ($message) => ! str_contains($message, 'Senha provisória'));

        expect(User::query()->where('email', 'maria@exemplo.com')->count())->toBe(1)->and($existing->fresh()->password)->toBe($hash);
    });

    it('lets the new administrator log in on the organization address only', function () {
        $this->actingAs(platformOperator());
        $this->post('/plataforma/organizations', ['name' => 'Liga das Ilhas', 'slug' => 'ilhas', 'plan' => 'basic', 'admin_name' => 'Maria', 'admin_email' => 'maria@exemplo.com']);
        $user = User::query()->where('email', 'maria@exemplo.com')->firstOrFail();
        auth()->logout();

        $this->actingAs($user)->get('http://ilhas.plataforma.test/painel')->assertOk();
        $this->actingAs($user)->get('http://localhost/painel')->assertForbidden();
    });

    it('validates the slug: format, uniqueness and reserved words', function () {
        otherOrganization();
        $this->actingAs(platformOperator());
        $data = ['name' => 'Liga', 'plan' => 'basic', 'admin_name' => 'Maria', 'admin_email' => 'maria@exemplo.com'];

        $this->post('/plataforma/organizations', $data + ['slug' => 'Liga Ilhas'])->assertSessionHasErrors('slug');
        $this->post('/plataforma/organizations', $data + ['slug' => 'vizinha'])->assertSessionHasErrors('slug');
        $this->post('/plataforma/organizations', $data + ['slug' => 'www'])->assertSessionHasErrors(['slug' => 'Esse endereço (slug) é reservado. Escolha outro.']);

        expect(Organization::query()->count())->toBe(2)->and(User::query()->where('email', 'maria@exemplo.com')->exists())->toBeFalse();
    });

    it('is closed to everyone but platform admins', function () {
        $this->actingAs(panelUser(Role::Admin));

        $this->get('/plataforma/organizations/create')->assertNotFound();
        $this->post('/plataforma/organizations', ['name' => 'X', 'slug' => 'x', 'plan' => 'basic', 'admin_name' => 'A', 'admin_email' => 'a@a.com'])->assertNotFound();
    });
});

describe('custom domains', function () {
    it('registers, validates and removes domains', function () {
        $vizinha = otherOrganization();
        $this->actingAs(platformOperator());
        $url = "/plataforma/organizations/{$vizinha->id}/domains";

        $this->post($url, ['domain' => ' LigaVizinha.com.br '])->assertSessionHas('success');
        expect(OrganizationDomain::query()->value('domain'))->toBe('ligavizinha.com.br');

        $this->post($url, ['domain' => 'ligavizinha.com.br'])->assertSessionHasErrors('domain');
        $this->post($url, ['domain' => 'https://outro.com.br/'])->assertSessionHasErrors('domain');
        $this->post($url, ['domain' => 'semponto'])->assertSessionHasErrors('domain');
        $this->post($url, ['domain' => 'qualquer.plataforma.test'])->assertSessionHasErrors('domain');

        $this->get("/plataforma/organizations/{$vizinha->id}")->assertInertia(fn ($page) => $page->where('domains.0.domain', 'ligavizinha.com.br')->where('subdomain', 'vizinha.plataforma.test'));

        $domain = OrganizationDomain::query()->firstOrFail();
        $this->delete("/plataforma/organizations/{$vizinha->id}/domains/{$domain->id}")->assertSessionHas('success');
        expect(OrganizationDomain::query()->count())->toBe(0);
    });

    it('does not remove a domain through another organization', function () {
        $vizinha = otherOrganization();
        $domain = OrganizationDomain::query()->create(['organization_id' => $vizinha->id, 'domain' => 'ligavizinha.com.br']);
        $this->actingAs(platformOperator());

        $this->delete('/plataforma/organizations/'.CurrentOrganization::id()."/domains/{$domain->id}")->assertNotFound();
        expect(OrganizationDomain::query()->count())->toBe(1);
    });
});

describe('white-label', function () {
    beforeEach(function () {
        Storage::fake('s3');
    });

    it('shows the organization brand in the portal and in the panel only with the feature', function () {
        $organization = CurrentOrganization::get();
        $this->actingAs(panelUser(Role::Admin));
        $this->post('/painel/organization/logo', ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertSessionHas('success');

        expect($organization->fresh()->logo_path)->not->toBeNull();

        $this->get('/')->assertInertia(fn ($page) => $page->where('theme.white_label', true)->where('theme.logo_url', fn ($url) => is_string($url) && str_contains($url, '128.webp')));
        $this->get('/painel')->assertInertia(fn ($page) => $page->where('brand.name', $organization->name)->where('brand.logo_url', fn ($url) => is_string($url)));

        $organization->update(['plan' => 'standard']);
        $this->get('/')->assertInertia(fn ($page) => $page->where('theme.white_label', false)->where('theme.logo_url', null)->where('theme.platform_name', config('app.name')));
        $this->get('/painel')->assertInertia(fn ($page) => $page->where('brand.name', config('app.name'))->where('brand.logo_url', null));
    });

    it('refuses the logo without the feature, and removes the files when the logo is removed', function () {
        $organization = CurrentOrganization::get();
        $this->actingAs(panelUser(Role::Admin));

        $organization->update(['plan' => 'standard']);
        $this->post('/painel/organization/logo', ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertSessionHas('error');
        expect($organization->fresh()->logo_path)->toBeNull();

        $organization->update(['plan' => 'pro']);
        $this->post('/painel/organization/logo', ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)]);
        $base = $organization->fresh()->logo_path;
        Storage::disk('s3')->assertExists("{$base}-128.webp");

        $this->delete('/painel/organization/logo')->assertSessionHas('success');
        Storage::disk('s3')->assertMissing("{$base}-128.webp");
        expect($organization->fresh()->logo_path)->toBeNull();
    });

    it('validates the logo file', function () {
        $this->actingAs(panelUser(Role::Admin));

        $this->post('/painel/organization/logo', ['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->post('/painel/organization/logo', [])->assertSessionHasErrors('logo');
    });

    it('lets only administrators change the brand', function () {
        $this->actingAs(panelUser(Role::Organizer));

        $this->post('/painel/organization/logo', ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertSessionHas('error');
        expect(CurrentOrganization::get()->fresh()->logo_path)->toBeNull()->and(CurrentOrganization::has(Feature::WhiteLabel))->toBeTrue();
    });
});

<?php

use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Registration\Models\Registration;
use App\Modules\Tenancy\Actions\PublishLegalDocument;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\LegalType;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\LegalAcceptance;
use App\Modules\Tenancy\Models\LegalDocument;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\LegalDocuments;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['name' => 'Liga das Ilhas', 'slug' => config('platform.default_organization_slug')]));
});

function publishLegal(LegalType $type, string $body = 'Texto oficial'): LegalDocument
{
    return app(PublishLegalDocument::class)->handle($type, $body);
}

describe('public pages', function () {
    it('shows the template with the organization data until a version is published', function () {
        CurrentOrganization::get()->update(['settings' => ['legal' => ['controller_name' => 'Associação Ilhas LTDA', 'contact_email' => 'lgpd@ilhas.org']]]);

        $this->get('/termos')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Portal/Legal')
            ->where('document.title', 'Termos de uso')
            ->where('document.version', null)
            ->where('document.body', fn ($body) => str_contains($body, 'Associação Ilhas LTDA')));

        $this->get('/privacidade')->assertInertia(fn ($page) => $page
            ->where('document.body', fn ($body) => str_contains($body, 'LGPD') && str_contains($body, 'lgpd@ilhas.org') && str_contains($body, 'hash'))
            ->where('contact_email', 'lgpd@ilhas.org'));
    });

    it('shows the latest published version', function () {
        publishLegal(LegalType::Privacy, 'Versão um');
        publishLegal(LegalType::Privacy, 'Versão dois');

        $this->get('/privacidade')->assertInertia(fn ($page) => $page->where('document.body', 'Versão dois')->where('document.version', 2));
    });

    it('keeps each organization with its own texts', function () {
        publishLegal(LegalType::Terms, 'Termos da liga A');
        config(['platform.base_domain' => 'plataforma.test']);
        Organization::factory()->create(['slug' => 'outra', 'name' => 'Outra Liga']);

        $this->get('http://outra.plataforma.test/termos')->assertInertia(fn ($page) => $page->where('document.version', null)->where('document.body', fn ($body) => ! str_contains($body, 'Termos da liga A')));
    });
});

describe('publishing', function () {
    it('creates numbered versions, never edits them and refuses empty or unchanged texts', function () {
        $first = publishLegal(LegalType::Terms, 'Um');
        $second = publishLegal(LegalType::Terms, 'Dois');

        expect($first->version)->toBe(1)->and($second->version)->toBe(2)->and(LegalDocument::query()->where('type', 'terms')->count())->toBe(2);
        expect(fn () => $first->update(['body' => 'adulterado']))->toThrow(LogicException::class);
        expect(fn () => publishLegal(LegalType::Terms, 'Dois'))->toThrow(InvalidArgumentException::class, 'O texto não mudou');
        expect(fn () => publishLegal(LegalType::Terms, '   '))->toThrow(InvalidArgumentException::class);
    });

    it('is done by administrators only, from the panel', function () {
        $admin = panelUser(Role::Admin);
        $this->actingAs($admin);

        $this->get('/painel/legal')->assertOk()->assertInertia(fn ($page) => $page->component('Panel/Legal/Edit')->has('documents', 2)->where('documents.0.is_default', true));
        $this->post('/painel/legal/publish', ['type' => 'terms', 'body' => 'Termos novos'])->assertSessionHas('success');
        $this->post('/painel/legal/publish', ['type' => 'terms', 'body' => 'Termos novos'])->assertSessionHas('error');
        $this->post('/painel/legal/publish', ['type' => 'outro', 'body' => 'x'])->assertSessionHasErrors('type');
        $this->post('/painel/legal/publish', ['type' => 'terms', 'body' => ''])->assertSessionHasErrors('body');

        expect(LegalDocument::query()->where('published_by', $admin->id)->count())->toBe(1);

        expect(LegalAcceptance::query()->where('user_id', $admin->id)->count())->toBe(1);

        $this->actingAs(panelUser(Role::Organizer));
        $this->post('/aceite-termos', ['accept' => true]);
        $this->get('/painel/legal')->assertForbidden();
        $this->post('/painel/legal/publish', ['type' => 'terms', 'body' => 'invasão'])->assertSessionHas('error');
        expect(LegalDocument::query()->count())->toBe(1);
    });

    it('saves the contact data used by the templates', function () {
        $this->actingAs(panelUser(Role::Admin));

        $this->patch('/painel/legal/settings', ['controller_name' => 'Associação X', 'contact_email' => 'lgpd@x.org'])->assertSessionHas('success');
        $this->patch('/painel/legal/settings', ['contact_email' => 'invalido'])->assertSessionHasErrors('contact_email');

        expect(LegalDocuments::settings())->toBe(['controller_name' => 'Associação X', 'contact_email' => 'lgpd@x.org']);
    });
});

describe('panel acceptance', function () {
    it('does not block anyone while only the template exists', function () {
        $this->actingAs(panelUser(Role::Viewer));

        $this->get('/painel')->assertOk();
    });

    it('blocks the panel until the published versions are accepted, recording each acceptance', function () {
        $terms = publishLegal(LegalType::Terms);
        $privacy = publishLegal(LegalType::Privacy);
        $user = panelUser(Role::Operator);
        $this->actingAs($user);

        $this->get('/painel')->assertRedirect('/aceite-termos');
        $this->get('/painel/teams')->assertRedirect('/aceite-termos');
        $this->get('/aceite-termos')->assertOk()->assertInertia(fn ($page) => $page->component('Legal/Accept')->has('documents', 2));

        $this->post('/aceite-termos', [])->assertSessionHasErrors('accept');
        // Quem só opera jogos volta para o app do mesário.
        $this->post('/aceite-termos', ['accept' => true])->assertRedirect('/painel/mesario');

        expect(LegalAcceptance::query()->where('user_id', $user->id)->pluck('legal_document_id')->sort()->values()->all())->toBe(collect([$terms->id, $privacy->id])->sort()->values()->all());
        $this->get('/painel')->assertOk();
        $this->get('/aceite-termos')->assertRedirect('/painel/mesario');
    });

    it('asks again only for the document that changed', function () {
        publishLegal(LegalType::Terms, 'Um');
        publishLegal(LegalType::Privacy, 'Privacidade');
        $this->actingAs(panelUser(Role::Viewer));
        $this->post('/aceite-termos', ['accept' => true]);

        publishLegal(LegalType::Terms, 'Dois');

        $this->get('/painel')->assertRedirect('/aceite-termos');
        $this->get('/aceite-termos')->assertInertia(fn ($page) => $page->has('documents', 1)->where('documents.0.title', 'Termos de uso')->where('documents.0.version', 2));
        $this->post('/aceite-termos', ['accept' => true]);

        expect(LegalAcceptance::query()->count())->toBe(3);
        $this->get('/painel')->assertOk();
    });

    it('requires login to see the acceptance screen', function () {
        $this->get('/aceite-termos')->assertRedirect('/login');
    });
});

describe('registration consent', function () {
    it('records which privacy version the team accepted', function () {
        Mail::fake();
        $privacy = publishLegal(LegalType::Privacy, 'Política vigente');
        $division = Division::factory()->create();
        $season = Season::query()->findOrFail($division->season_id);
        $season->update(['status' => 'registration']);

        $this->post("/inscricoes/{$season->competition->slug}/{$season->id}", [
            'division_id' => $division->id, 'team_name' => 'Time X', 'contact_name' => 'Ana', 'contact_email' => 'ana@exemplo.com', 'consent' => true,
        ]);

        expect(Registration::query()->firstOrFail()->consent_document_id)->toBe($privacy->id);

        $this->actingAs(panelUser(Role::Admin));
        $this->post('/aceite-termos', ['accept' => true]);
        $this->get('/painel/registrations/'.Registration::query()->firstOrFail()->id)->assertInertia(fn ($page) => $page->where('registration.consent_version', 1));
    });

    it('leaves the version empty while the template is in use', function () {
        Mail::fake();
        $division = Division::factory()->create();
        $season = Season::query()->findOrFail($division->season_id);
        $season->update(['status' => 'registration']);

        $this->post("/inscricoes/{$season->competition->slug}/{$season->id}", [
            'division_id' => $division->id, 'team_name' => 'Time X', 'contact_name' => 'Ana', 'contact_email' => 'ana@exemplo.com', 'consent' => true,
        ]);

        expect(Registration::query()->firstOrFail()->consent_document_id)->toBeNull();
    });
});

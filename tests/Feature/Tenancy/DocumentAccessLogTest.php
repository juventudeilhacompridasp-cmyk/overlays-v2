<?php

use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Registration\Enums\DocumentType;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationDocument;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\PersonDocument;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\DocumentAccess;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['sheet_import']]));
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://signed.test/{$path}?sig=1");
});

describe('recording who opened a personal document', function () {
    it('records the person document opening with who opened it and which document', function () {
        $person = Person::factory()->create(['name' => 'Fulano de Tal']);
        $document = PersonDocument::factory()->for($person)->create();
        $organizer = panelUser(Role::Organizer);

        $this->actingAs($organizer)->get(route('panel.people.documents.show', $document))->assertRedirectContains('https://signed.test/');

        $entry = DocumentAccess::query()->firstOrFail();
        expect($entry->user_id)->toBe($organizer->id)
            ->and($entry->actor_name)->toBe($organizer->name)
            ->and($entry->kind)->toBe('person_document')
            ->and($entry->label)->toBe('Atestado médico — Fulano de Tal');
    });

    it('records the registration document opening', function () {
        $registration = Registration::factory()->create();
        $registrant = RegistrationPerson::query()->create(['organization_id' => $registration->organization_id, 'registration_id' => $registration->id, 'role' => 'athlete', 'name' => 'Ciclano Souza']);
        $document = RegistrationDocument::query()->create([
            'organization_id' => $registration->organization_id,
            'registration_person_id' => $registrant->id,
            'type' => DocumentType::Medical,
            'path' => 'organizations/x/registrations/y/z.pdf',
            'original_name' => 'atestado.pdf',
            'mime' => 'application/pdf',
            'size' => 100,
        ]);

        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.registrations.documents.show', $document))->assertRedirectContains('https://signed.test/');

        $entry = DocumentAccess::query()->firstOrFail();
        expect($entry->kind)->toBe('registration_document')
            ->and($entry->label)->toBe('Atestado médico — Ciclano Souza');
    });

    it('records the sent sheet opening with the teams of the game', function () {
        $game = Game::factory()->create();
        $this->actingAs(panelUser(Role::Operator))->post(route('panel.games.uploads.store', $game), ['file' => UploadedFile::fake()->create('sumula.pdf', 100, 'application/pdf')]);
        $upload = GameSheetUpload::query()->firstOrFail();

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.show', $upload))->assertRedirectContains('https://signed.test/');

        $entry = DocumentAccess::query()->firstOrFail();
        expect($entry->kind)->toBe('sheet_upload')
            ->and($entry->label)->toContain('Súmula enviada — ')
            ->and($entry->label)->toContain(' x ');
    });

    it('does not record an opening that was refused for lack of permission', function () {
        $document = PersonDocument::factory()->create();

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.people.documents.show', $document))->assertForbidden();

        expect(DocumentAccess::query()->count())->toBe(0);
    });

    it('never stores IP, CPF or the file content in the record', function () {
        expect(Schema::hasColumns('document_access_log', ['id', 'organization_id', 'user_id', 'actor_name', 'kind', 'label', 'created_at']))->toBeTrue()
            ->and(Schema::hasColumn('document_access_log', 'ip'))->toBeFalse()
            ->and(Schema::hasColumn('document_access_log', 'user_agent'))->toBeFalse();
    });
});

describe('the access screen', function () {
    it('shows the latest accesses to the administrator only', function () {
        DocumentAccess::query()->create(['actor_name' => 'Ana', 'kind' => 'person_document', 'label' => 'Atestado médico — Beto']);
        DocumentAccess::query()->create(['actor_name' => 'Caio', 'kind' => 'sheet_upload', 'label' => 'Súmula enviada — A x B']);

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.document-access.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/DocumentAccess/Index')
                ->has('entries', 2)
                ->where('entries.0.kind_label', 'Súmula enviada')
                ->where('entries.1.actor', 'Ana'));

        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.document-access.index'))->assertForbidden();
        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.document-access.index'))->assertForbidden();
    });

    it('never shows accesses of another organization', function () {
        $other = Organization::factory()->create();
        DocumentAccess::query()->withoutGlobalScopes()->create(['organization_id' => $other->id, 'actor_name' => 'Intruso', 'kind' => 'person_document', 'label' => 'Atestado médico — Outro']);

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.document-access.index'))->assertInertia(fn ($page) => $page->has('entries', 0));
    });

    it('is closed to guests', function () {
        $this->get(route('panel.document-access.index'))->assertRedirect('/login');
    });
});

describe('pruning', function () {
    it('deletes only records older than two years', function () {
        $old = DocumentAccess::query()->create(['actor_name' => 'Velho', 'kind' => 'person_document', 'label' => 'x']);
        $old->forceFill(['created_at' => now()->subDays(731)])->save();
        $recent = DocumentAccess::query()->create(['actor_name' => 'Novo', 'kind' => 'person_document', 'label' => 'y']);

        $this->artisan('documents:prune-access-log')->assertSuccessful();

        expect(DocumentAccess::query()->pluck('id')->all())->toBe([$recent->id]);
    });
});

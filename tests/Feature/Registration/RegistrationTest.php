<?php

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Enums\ReviewStatus;
use App\Modules\Registration\Mail\RegistrationMail;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationDocument;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Support\Cpf;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://signed.test/{$path}?sig=1");
    Mail::fake();
});

/** @return array{season: Season, division: Division} */
function openSeason(array $settings = []): array
{
    $division = Division::factory()->create();
    $season = Season::query()->findOrFail($division->season_id);
    $season->update(['status' => SeasonStatus::Registration, 'registration' => $settings + ['min_athletes' => 2, 'max_athletes' => 4, 'require_documents' => true]]);

    return ['season' => $season->fresh(), 'division' => $division];
}

function applyData(Division $division, array $override = []): array
{
    return $override + [
        'division_id' => $division->id, 'team_name' => 'Ilha FC', 'team_short_name' => 'ILH', 'team_city' => 'Ilhabela',
        'contact_name' => 'Maria Responsável', 'contact_email' => 'maria@exemplo.com', 'contact_phone' => '12999990000', 'consent' => true,
    ];
}

function startedRegistration(array $settings = [], array $override = []): Registration
{
    ['season' => $season, 'division' => $division] = openSeason($settings);

    test()->post("/inscricoes/{$season->competition->slug}/{$season->id}", applyData($division, $override));

    return Registration::query()->orderByDesc('id')->firstOrFail();
}

function addPerson(Registration $registration, array $override = []): RegistrationPerson
{
    test()->post("{$registration->manageUrl()}/pessoas", $override + ['role' => 'athlete', 'name' => 'Atleta '.fake()->unique()->lastName(), 'birth_date' => '1995-05-05']);

    return RegistrationPerson::query()->where('registration_id', $registration->id)->orderByDesc('id')->firstOrFail();
}

function pdf(string $name = 'rg.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function readySubmitted(): Registration
{
    $registration = startedRegistration();

    foreach ([['athlete', 10], ['athlete', 7]] as [$role, $shirt]) {
        $person = addPerson($registration, ['role' => $role, 'shirt_number' => $shirt]);
        test()->post("{$registration->manageUrl()}/pessoas/{$person->id}/documentos", ['type' => 'id_document', 'file' => pdf()]);
    }

    addPerson($registration, ['role' => 'coach', 'name' => 'Técnico Teste', 'birth_date' => null]);
    test()->post("{$registration->manageUrl()}/enviar");

    return $registration->fresh();
}

describe('application form', function () {
    it('shows the form of a season with open registration', function () {
        ['season' => $season, 'division' => $division] = openSeason(['instructions' => 'Levar documentos originais', 'deadline' => now()->addWeek()->toDateString()]);

        $this->get("/inscricoes/{$season->competition->slug}/{$season->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Registration/Apply')
            ->where('open', true)
            ->where('instructions', 'Levar documentos originais')
            ->where('requirements.min_athletes', 2)
            ->where('divisions.0.id', $division->id));
    });

    it('is closed when the season is not in registration or the deadline has passed', function () {
        ['season' => $season, 'division' => $division] = openSeason(['deadline' => now()->subDay()->toDateString()]);
        $url = "/inscricoes/{$season->competition->slug}/{$season->id}";

        $this->get($url)->assertInertia(fn ($page) => $page->where('open', false));
        $this->post($url, applyData($division))->assertSessionHas('error', 'As inscrições desta edição não estão abertas.');

        $season->update(['status' => SeasonStatus::Active, 'registration' => ['deadline' => null]]);
        $this->get($url)->assertInertia(fn ($page) => $page->where('open', false));
        expect(Registration::query()->count())->toBe(0);
    });

    it('hides draft seasons and mismatched competitions', function () {
        ['season' => $season] = openSeason();
        $other = Competition::factory()->create();

        $this->get("/inscricoes/{$other->slug}/{$season->id}")->assertNotFound();

        $season->update(['status' => SeasonStatus::Draft]);
        $this->get("/inscricoes/{$season->competition->slug}/{$season->id}")->assertNotFound();
    });

    it('creates the request, sends the personal link by e-mail and opens the team area', function () {
        ['season' => $season, 'division' => $division] = openSeason();

        $response = $this->post("/inscricoes/{$season->competition->slug}/{$season->id}", applyData($division));
        $registration = Registration::query()->firstOrFail();

        expect(strlen($registration->token))->toBe(40)
            ->and($registration->status)->toBe(RegistrationStatus::Draft)
            ->and($registration->consent_at)->not->toBeNull()
            ->and($registration->contact_email)->toBe('maria@exemplo.com');

        $response->assertRedirect($registration->manageUrl());
        Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail) => $mail->hasTo('maria@exemplo.com') && $mail->registration->is($registration));
    });

    it('requires the privacy consent and valid data', function () {
        ['season' => $season, 'division' => $division] = openSeason();
        $url = "/inscricoes/{$season->competition->slug}/{$season->id}";

        $this->post($url, applyData($division, ['consent' => false]))->assertSessionHasErrors(['consent' => 'É preciso aceitar o aviso de privacidade para continuar.']);
        $this->post($url, applyData($division, ['contact_email' => 'invalido', 'team_name' => '']))->assertSessionHasErrors(['contact_email', 'team_name']);
        $this->post($url, applyData($division, ['division_id' => Division::factory()->create()->id]))->assertSessionHas('error', 'Escolha uma divisão desta edição.');
    });

    it('refuses a second request for the same team in the division, and teams already entered', function () {
        ['season' => $season, 'division' => $division] = openSeason();
        $url = "/inscricoes/{$season->competition->slug}/{$season->id}";

        $this->post($url, applyData($division));
        $this->post($url, applyData($division, ['team_name' => 'ilha fc']))->assertSessionHas('error');

        $team = Team::factory()->create(['name' => 'Já Inscrito']);
        Entry::factory()->create(['division_id' => $division->id, 'team_id' => $team->id]);
        $this->post($url, applyData($division, ['team_name' => 'Já Inscrito']))->assertSessionHas('error', 'Esta equipe já está inscrita nesta divisão.');

        expect(Registration::query()->count())->toBe(1);
    });
});

describe('team area', function () {
    it('opens only with the personal token and never shows the CPF', function () {
        $registration = startedRegistration();
        $person = addPerson($registration, ['document' => '529.982.247-25']);

        $this->get($registration->manageUrl())->assertOk()->assertInertia(fn ($page) => $page
            ->component('Registration/Manage')
            ->where('registration.team_name', 'Ilha FC')
            ->where('registration.editable', true)
            ->has('people', 1)
            ->where('people.0.has_document', true)
            ->has('problems'));

        $json = json_encode($this->get($registration->manageUrl())->original->getData()['page']['props']);
        expect($json)->not->toContain('52998224725')->not->toContain($person->document_hash);

        $this->get('/minha-inscricao/'.str_repeat('x', 40))->assertNotFound();
    });

    it('adds athletes and staff, keeping the CPF only as a hash', function () {
        $registration = startedRegistration();

        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Rafael Souza', 'birth_date' => '1995-05-05', 'document' => '529.982.247-25', 'shirt_number' => 10])->assertSessionHas('success');

        $person = RegistrationPerson::query()->firstOrFail();
        expect($person->document_hash)->toBe(Cpf::hash('52998224725'))
            ->and($person->shirt_number)->toBe(10)
            ->and($person->review_status)->toBe(ReviewStatus::Pending);

        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'coach', 'name' => 'Técnico Silva'])->assertSessionHas('success');
        expect(RegistrationPerson::query()->count())->toBe(2);
    });

    it('validates athletes: birth date, shirt, CPF and limits', function () {
        $registration = startedRegistration();
        addPerson($registration, ['name' => 'Primeiro', 'shirt_number' => 9, 'document' => '529.982.247-25']);
        $url = "{$registration->manageUrl()}/pessoas";

        $this->post($url, ['role' => 'athlete', 'name' => 'Sem Data'])->assertSessionHasErrors(['birth_date' => 'Informe a data de nascimento do atleta.']);
        $this->post($url, ['role' => 'athlete', 'name' => 'Mesma Camisa', 'birth_date' => '1990-01-01', 'shirt_number' => 9])->assertSessionHasErrors(['shirt_number' => 'A camisa 9 já está com outro atleta.']);
        $this->post($url, ['role' => 'athlete', 'name' => 'CPF Ruim', 'birth_date' => '1990-01-01', 'document' => '111.111.111-11'])->assertSessionHasErrors(['document' => 'O CPF informado não é válido.']);
        $this->post($url, ['role' => 'athlete', 'name' => 'CPF Repetido', 'birth_date' => '1990-01-01', 'document' => '52998224725'])->assertSessionHasErrors(['document' => 'Este CPF já foi informado neste pedido.']);

        foreach (range(1, 3) as $n) {
            addPerson($registration, ['name' => "Atleta {$n}", 'shirt_number' => $n]);
        }

        $this->post($url, ['role' => 'athlete', 'name' => 'Excedente', 'birth_date' => '1990-01-01'])->assertSessionHasErrors(['name' => 'O limite é de 4 atletas por equipe nesta edição.']);
    });

    it('requires a guardian and the authorization for minors and clears them for adults', function () {
        $registration = startedRegistration();
        $url = "{$registration->manageUrl()}/pessoas";
        $minor = now()->subYears(15)->toDateString();

        $this->post($url, ['role' => 'athlete', 'name' => 'Pedro', 'birth_date' => $minor])->assertSessionHasErrors('guardian_name');
        $this->post($url, ['role' => 'athlete', 'name' => 'Pedro', 'birth_date' => $minor, 'guardian_name' => 'Ana Mãe'])->assertSessionHasErrors('guardian_name');

        $this->post($url, ['role' => 'athlete', 'name' => 'Pedro', 'birth_date' => $minor, 'guardian_name' => 'Ana Mãe', 'guardian_consent' => true])->assertSessionHas('success');
        $person = RegistrationPerson::query()->firstOrFail();
        expect($person->isMinor())->toBeTrue()->and($person->guardian_name)->toBe('Ana Mãe')->and($person->guardian_consent_at)->not->toBeNull();

        $this->patch("{$url}/{$person->id}", ['role' => 'athlete', 'name' => 'Pedro', 'birth_date' => '1990-01-01', 'guardian_name' => 'Ana Mãe', 'guardian_consent' => true]);
        expect($person->fresh()->guardian_name)->toBeNull()->and($person->fresh()->guardian_consent_at)->toBeNull();
    });

    it('edits and removes people, but only the ones of its own request', function () {
        $registration = startedRegistration();
        $person = addPerson($registration, ['name' => 'Antes']);

        $this->patch("{$registration->manageUrl()}/pessoas/{$person->id}", ['role' => 'athlete', 'name' => 'Depois', 'birth_date' => '1995-05-05'])->assertSessionHas('success');
        expect($person->fresh()->name)->toBe('Depois');

        $other = Registration::factory()->create();
        $foreign = RegistrationPerson::query()->create(['organization_id' => $other->organization_id, 'registration_id' => $other->id, 'role' => RosterRole::Athlete, 'name' => 'Outro', 'review_status' => ReviewStatus::Pending]);

        $this->patch("{$registration->manageUrl()}/pessoas/{$foreign->id}", ['role' => 'athlete', 'name' => 'Invasão', 'birth_date' => '1995-05-05'])->assertNotFound();
        $this->delete("{$registration->manageUrl()}/pessoas/{$foreign->id}")->assertNotFound();

        $this->delete("{$registration->manageUrl()}/pessoas/{$person->id}")->assertSessionHas('success');
        expect(RegistrationPerson::query()->whereKey($person->id)->exists())->toBeFalse();
    });

    it('updates the team data', function () {
        $registration = startedRegistration();

        $this->patch("{$registration->manageUrl()}/equipe", ['team_name' => 'Ilha Unida', 'team_short_name' => 'IUN', 'team_city' => 'Ubatuba', 'contact_name' => 'João', 'contact_phone' => '12'])->assertSessionHas('success');

        expect($registration->fresh()->team_name)->toBe('Ilha Unida');
    });
});

describe('documents', function () {
    it('stores documents on the private disk and removes them with the person', function () {
        $registration = startedRegistration();
        $person = addPerson($registration);

        $this->post("{$registration->manageUrl()}/pessoas/{$person->id}/documentos", ['type' => 'medical', 'file' => pdf('atestado.pdf')])->assertSessionHas('success', 'Documento enviado.');

        $document = RegistrationDocument::query()->firstOrFail();
        expect($document->path)->toStartWith("organizations/{$registration->organization_id}/registrations/{$registration->id}/{$person->id}/")
            ->and($document->original_name)->toBe('atestado.pdf')
            ->and($document->mime)->toBe('application/pdf');
        Storage::disk('s3-private')->assertExists($document->path);

        $this->delete("{$registration->manageUrl()}/pessoas/{$person->id}")->assertSessionHas('success');
        Storage::disk('s3-private')->assertMissing($document->path);
        expect(RegistrationDocument::query()->count())->toBe(0);
    });

    it('removes a single document', function () {
        $registration = startedRegistration();
        $person = addPerson($registration);
        $this->post("{$registration->manageUrl()}/pessoas/{$person->id}/documentos", ['type' => 'id_document', 'file' => pdf()]);
        $document = RegistrationDocument::query()->firstOrFail();

        $this->delete("{$registration->manageUrl()}/pessoas/{$person->id}/documentos/{$document->id}")->assertSessionHas('success');

        Storage::disk('s3-private')->assertMissing($document->path);
    });

    it('accepts only pdf and images up to 5 MB, six per person', function () {
        $registration = startedRegistration();
        $person = addPerson($registration);
        $url = "{$registration->manageUrl()}/pessoas/{$person->id}/documentos";

        $this->post($url, ['type' => 'other', 'file' => UploadedFile::fake()->createWithContent('x.exe', 'MZ binário')])->assertSessionHasErrors('file');
        $this->post($url, ['type' => 'other', 'file' => UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf')])->assertSessionHasErrors(['file' => 'O arquivo do campo arquivo não pode ter mais de 5120 KB.']);
        $this->post($url, ['type' => 'nada', 'file' => pdf()])->assertSessionHasErrors('type');

        foreach (range(1, 6) as $n) {
            $this->post($url, ['type' => 'other', 'file' => UploadedFile::fake()->image("foto{$n}.jpg", 200, 200)]);
        }
        $this->post($url, ['type' => 'other', 'file' => pdf()])->assertSessionHas('error', 'No máximo 6 documentos por pessoa.');

        expect($person->documents()->count())->toBe(6);
    });
});

describe('submission', function () {
    it('lists what is missing before it can be sent', function () {
        $registration = startedRegistration();
        addPerson($registration, ['shirt_number' => 1]);

        $this->get($registration->manageUrl())->assertInertia(fn ($page) => $page
            ->where('problems.0', 'Inscreva pelo menos 2 atletas (hoje: 1).')
            ->where('problems.1', 'Informe pelo menos um membro da comissão (técnico, auxiliar, massagista ou dirigente).')
            ->where('problems.2', fn ($text) => str_starts_with($text, 'Faltam documentos de:')));

        $this->post("{$registration->manageUrl()}/enviar")->assertSessionHas('error');
        expect($registration->fresh()->status)->toBe(RegistrationStatus::Draft);
    });

    it('does not require documents when the season does not ask for them', function () {
        $registration = startedRegistration(['require_documents' => false]);
        addPerson($registration, ['shirt_number' => 1]);
        addPerson($registration, ['shirt_number' => 2]);
        addPerson($registration, ['role' => 'manager', 'name' => 'Dirigente', 'birth_date' => null]);

        $this->post("{$registration->manageUrl()}/enviar")->assertSessionHas('success');
        expect($registration->fresh()->status)->toBe(RegistrationStatus::Submitted);
    });

    it('sends the request for review and locks the team area', function () {
        $registration = readySubmitted();

        expect($registration->status)->toBe(RegistrationStatus::Submitted)->and($registration->submitted_at)->not->toBeNull();

        $this->post("{$registration->manageUrl()}/pessoas", ['role' => 'athlete', 'name' => 'Tardio', 'birth_date' => '1990-01-01'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'já foi enviada'));
        $this->patch("{$registration->manageUrl()}/equipe", ['team_name' => 'Outro Nome', 'contact_name' => 'X'])->assertSessionHas('error');
        $this->get($registration->manageUrl())->assertInertia(fn ($page) => $page->where('registration.editable', false)->where('problems', []));

        expect($registration->fresh()->team_name)->toBe('Ilha FC')->and($registration->people()->count())->toBe(3);
    });
});

describe('panel review', function () {
    it('keeps registrations away from roles that cannot manage competitions, even for reading', function (Role $role) {
        $registration = readySubmitted();
        $document = RegistrationDocument::query()->firstOrFail();
        $this->actingAs(panelUser($role));

        $this->get('/painel/registrations')->assertForbidden();
        $this->get("/painel/registrations/{$registration->id}")->assertForbidden();
        $this->get("/painel/registration-documents/{$document->id}")->assertForbidden();
        $this->post("/painel/registrations/{$registration->id}/approve")->assertSessionHas('error');
    })->with([Role::Operator, Role::Viewer]);

    it('lists the requests with the ones waiting for review first and filters by status', function () {
        $draft = startedRegistration(['min_athletes' => 2], ['team_name' => 'Rascunho FC']);
        $submitted = readySubmitted();
        $this->actingAs(panelUser(Role::Organizer));

        $this->get('/painel/registrations')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Panel/Registrations/Index')
            ->where('pending', 1)
            ->where('registrations.data.0.id', $submitted->id)
            ->where('registrations.data.0.athletes_count', 2)
            ->where('registrations.data.1.id', $draft->id));

        $this->get('/painel/registrations?status=draft')->assertInertia(fn ($page) => $page->has('registrations.data', 1)->where('registrations.data.0.id', $draft->id));
    });

    it('shows the request with documents behind signed urls and no CPF', function () {
        $registration = readySubmitted();
        $document = RegistrationDocument::query()->firstOrFail();
        $this->actingAs(panelUser(Role::Organizer));

        $response = $this->get("/painel/registrations/{$registration->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Panel/Registrations/Show')
            ->where('registration.reviewable', true)
            ->has('people', 3)
            ->where('requirements.min_athletes', 2));

        expect(json_encode($response->original->getData()['page']['props']))->not->toContain('document_hash');

        $this->get("/painel/registration-documents/{$document->id}")->assertRedirect("https://signed.test/{$document->path}?sig=1");
    });

    it('needs a reason to reject a person and decides each one', function () {
        $registration = readySubmitted();
        $person = $registration->people()->where('role', RosterRole::Athlete)->firstOrFail();
        $this->actingAs(panelUser(Role::Organizer));
        $url = "/painel/registrations/{$registration->id}/people/{$person->id}";

        $this->patch($url, ['review_status' => 'rejected'])->assertSessionHas('error', 'Explique o motivo da recusa para a equipe poder corrigir.');
        $this->patch($url, ['review_status' => 'rejected', 'review_note' => 'Documento ilegível'])->assertSessionHas('success');
        expect($person->fresh()->review_status)->toBe(ReviewStatus::Rejected)->and($person->fresh()->review_note)->toBe('Documento ilegível');

        $this->patch($url, ['review_status' => 'approved'])->assertSessionHas('success');
        expect($person->fresh()->review_status)->toBe(ReviewStatus::Approved)->and($person->fresh()->review_note)->toBeNull();

        $this->patch($url, ['review_status' => 'banana'])->assertSessionHasErrors('review_status');
    });

    it('returns the request for changes, lets the team fix it and send it again with a fresh review', function () {
        $registration = readySubmitted();
        $person = $registration->people()->where('role', RosterRole::Athlete)->firstOrFail();
        $this->actingAs(panelUser(Role::Organizer));
        $this->patch("/painel/registrations/{$registration->id}/people/{$person->id}", ['review_status' => 'approved']);

        $this->post("/painel/registrations/{$registration->id}/return", ['note' => ''])->assertSessionHasErrors('note');
        $this->post("/painel/registrations/{$registration->id}/return", ['note' => 'Falta a foto do RG'])->assertSessionHas('success');

        expect($registration->fresh()->status)->toBe(RegistrationStatus::Returned)->and($registration->fresh()->review_note)->toBe('Falta a foto do RG');
        Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail) => $mail->note === 'Falta a foto do RG' && $mail->hasTo('maria@exemplo.com'));

        auth()->logout();
        $this->get($registration->manageUrl())->assertInertia(fn ($page) => $page->where('registration.editable', true)->where('registration.review_note', 'Falta a foto do RG'));
        $this->post("{$registration->manageUrl()}/enviar")->assertSessionHas('success');

        expect($registration->fresh()->status)->toBe(RegistrationStatus::Submitted)->and($person->fresh()->review_status)->toBe(ReviewStatus::Pending);
    });

    it('rejects a request with a reason and tells the team', function () {
        $registration = readySubmitted();
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/registrations/{$registration->id}/reject", [])->assertSessionHasErrors('note');
        $this->post("/painel/registrations/{$registration->id}/reject", ['note' => 'Fora do prazo regulamentar'])->assertSessionHas('success');

        expect($registration->fresh()->status)->toBe(RegistrationStatus::Rejected);
        Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail) => $mail->note === 'Fora do prazo regulamentar');
    });

    it('only reviews requests that are under review', function () {
        $draft = startedRegistration();
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/registrations/{$draft->id}/approve")->assertSessionHas('error', 'Só pedidos em análise podem ser revisados.');
        $this->post("/painel/registrations/{$draft->id}/reject", ['note' => 'x'])->assertSessionHas('error', 'Só pedidos em análise podem ser revisados.');
    });
});

describe('approval', function () {
    function approveAll(Registration $registration): void
    {
        foreach ($registration->people as $person) {
            $person->update(['review_status' => ReviewStatus::Approved]);
        }
    }

    it('creates the team, the confirmed entry and the roster, and tells the team', function () {
        $registration = readySubmitted();
        $registration->load('people');
        approveAll($registration);
        $this->actingAs($admin = panelUser(Role::Organizer));

        $this->post("/painel/registrations/{$registration->id}/approve")->assertSessionHas('success');

        $registration->refresh();
        $team = Team::query()->where('name', 'Ilha FC')->firstOrFail();
        $entry = Entry::query()->findOrFail($registration->entry_id);

        expect($registration->status)->toBe(RegistrationStatus::Approved)
            ->and($registration->team_id)->toBe($team->id)
            ->and($registration->reviewed_by)->toBe($admin->id)
            ->and($team->short_name)->toBe('ILH')->and($team->city)->toBe('Ilhabela')
            ->and($entry->status)->toBe(EntryStatus::Confirmed)->and($entry->division_id)->toBe($registration->division_id)
            ->and(RosterEntry::query()->where('entry_id', $entry->id)->count())->toBe(3)
            ->and(RosterEntry::query()->where('entry_id', $entry->id)->where('shirt_number', 10)->exists())->toBeTrue()
            ->and($registration->people()->whereNull('person_id')->count())->toBe(0);

        Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail) => str_contains($mail->subjectLine, 'aprovada'));
    });

    it('does not duplicate a person who is already registered with the same CPF', function () {
        $existing = Person::factory()->create(['name' => 'Cadastro Antigo', 'document_hash' => Cpf::hash('52998224725')]);
        $registration = startedRegistration(['require_documents' => false]);
        addPerson($registration, ['name' => 'Rafael Novo Nome', 'document' => '529.982.247-25', 'shirt_number' => 10]);
        addPerson($registration, ['shirt_number' => 7]);
        addPerson($registration, ['role' => 'coach', 'name' => 'Técnico', 'birth_date' => null]);
        $this->post("{$registration->manageUrl()}/enviar");
        approveAll($registration->fresh()->load('people'));
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/registrations/{$registration->id}/approve")->assertSessionHas('success');

        expect(Person::query()->where('document_hash', Cpf::hash('52998224725'))->count())->toBe(1)
            ->and(Person::query()->find($existing->id)->name)->toBe('Cadastro Antigo')
            ->and(RegistrationPerson::query()->where('name', 'Rafael Novo Nome')->firstOrFail()->person_id)->toBe($existing->id);
    });

    it('needs every person reviewed and enough approved athletes', function () {
        $registration = readySubmitted();
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/registrations/{$registration->id}/approve")->assertSessionHas('error', 'Revise todos os atletas e membros da comissão antes de aprovar.');

        $registration->load('people');
        approveAll($registration);
        $athlete = $registration->people->firstWhere('role', RosterRole::Athlete);
        $athlete->update(['review_status' => ReviewStatus::Rejected, 'review_note' => 'Sem documento']);

        $this->post("/painel/registrations/{$registration->id}/approve")->assertSessionHas('error', 'É preciso aprovar pelo menos 2 atletas para aprovar a inscrição.');
        expect(Team::query()->where('name', 'Ilha FC')->exists())->toBeFalse()->and($registration->fresh()->status)->toBe(RegistrationStatus::Submitted);
    });

    it('skips the people who were rejected', function () {
        $registration = startedRegistration(['require_documents' => false, 'min_athletes' => 1]);
        addPerson($registration, ['name' => 'Fica', 'shirt_number' => 1]);
        addPerson($registration, ['name' => 'Sai', 'shirt_number' => 2]);
        addPerson($registration, ['role' => 'coach', 'name' => 'Técnico', 'birth_date' => null]);
        $this->post("{$registration->manageUrl()}/enviar");
        $registration->refresh()->load('people');
        approveAll($registration);
        $registration->people->firstWhere('name', 'Sai')->update(['review_status' => ReviewStatus::Rejected, 'review_note' => 'Duplicado']);
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/registrations/{$registration->id}/approve")->assertSessionHas('success');

        expect(Person::query()->where('name', 'Fica')->exists())->toBeTrue()->and(Person::query()->where('name', 'Sai')->exists())->toBeFalse();
    });

    it('asks to link an existing team when the name is already taken, and creates nothing', function () {
        Team::factory()->create(['name' => 'Ilha FC']);
        $registration = readySubmitted()->load('people');
        approveAll($registration);
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/registrations/{$registration->id}/approve")->assertSessionHas('error', fn ($message) => str_contains($message, 'Já existe uma equipe chamada "Ilha FC"'));
        expect(Entry::query()->count())->toBe(0)->and($registration->fresh()->status)->toBe(RegistrationStatus::Submitted);

        $existing = Team::query()->where('name', 'Ilha FC')->firstOrFail();
        $this->post("/painel/registrations/{$registration->id}/approve", ['team_id' => $existing->id])->assertSessionHas('success');

        expect($registration->fresh()->team_id)->toBe($existing->id)->and(Team::query()->where('name', 'Ilha FC')->count())->toBe(1);
    });

    it('rolls everything back when a roster rule fails (athlete already active in the division)', function () {
        $registration = readySubmitted()->load('people');
        approveAll($registration);
        $athlete = $registration->people->firstWhere('role', RosterRole::Athlete);
        $otherEntry = Entry::factory()->create(['division_id' => $registration->division_id]);
        $person = Person::factory()->create(['document_hash' => 'hash-compartilhado']);
        RosterEntry::factory()->create(['entry_id' => $otherEntry->id, 'person_id' => $person->id]);
        $athlete->update(['document_hash' => 'hash-compartilhado']);
        $this->actingAs(panelUser(Role::Organizer));

        $this->post("/painel/registrations/{$registration->id}/approve")->assertSessionHas('error', fn ($message) => str_contains($message, 'já é atleta de'));

        expect(Team::query()->where('name', 'Ilha FC')->exists())->toBeFalse()
            ->and(Entry::query()->where('division_id', $registration->division_id)->count())->toBe(1)
            ->and($registration->fresh()->status)->toBe(RegistrationStatus::Submitted);
    });

    it('keeps approved requests as history and deletes the others with their documents', function () {
        $registration = readySubmitted()->load('people');
        approveAll($registration);
        $this->actingAs(panelUser(Role::Organizer));
        $this->post("/painel/registrations/{$registration->id}/approve");

        $this->delete("/painel/registrations/{$registration->id}")->assertSessionHas('error');
        expect(Registration::query()->whereKey($registration->id)->exists())->toBeTrue();

        $draft = startedRegistration([], ['team_name' => 'Outro FC']);
        $person = addPerson($draft);
        $this->post("{$draft->manageUrl()}/pessoas/{$person->id}/documentos", ['type' => 'id_document', 'file' => pdf()]);
        $path = RegistrationDocument::query()->where('registration_person_id', $person->id)->firstOrFail()->path;

        $this->delete("/painel/registrations/{$draft->id}")->assertRedirect(route('panel.registrations.index'));
        expect(Registration::query()->whereKey($draft->id)->exists())->toBeFalse();
        Storage::disk('s3-private')->assertMissing($path);
    });
});

describe('season settings', function () {
    it('saves the registration rules and shows them with the public link and pending count', function () {
        ['season' => $season] = openSeason();
        $this->actingAs(panelUser(Role::Organizer));

        $this->patch("/painel/seasons/{$season->id}/registration", ['deadline' => '2030-01-31', 'instructions' => 'Levar RG', 'min_athletes' => 11, 'max_athletes' => 25, 'require_documents' => false])->assertSessionHas('success');

        expect($season->fresh()->registrationSettings())->toBe(['deadline' => '2030-01-31', 'instructions' => 'Levar RG', 'min_athletes' => 11, 'max_athletes' => 25, 'require_documents' => false]);

        $this->get("/painel/seasons/{$season->id}")->assertInertia(fn ($page) => $page
            ->where('registration.min_athletes', 11)
            ->where('registration.open', true)
            ->where('registration.apply_url', route('registration.apply', [$season->competition->slug, $season->id]))
            ->where('registration.pending', 0));
    });

    it('validates and refuses roles without permission', function () {
        ['season' => $season] = openSeason();
        $this->actingAs(panelUser(Role::Organizer));

        $this->patch("/painel/seasons/{$season->id}/registration", ['min_athletes' => 12, 'max_athletes' => 5, 'require_documents' => true])->assertSessionHasErrors('max_athletes');

        $this->actingAs(panelUser(Role::Operator));
        $this->patch("/painel/seasons/{$season->id}/registration", ['min_athletes' => 5, 'max_athletes' => 20, 'require_documents' => true])->assertSessionHas('error');
    });

    it('uses sensible defaults when nothing was configured', function () {
        $season = Season::factory()->create(['status' => SeasonStatus::Registration]);

        expect($season->registrationSettings())->toBe(['deadline' => null, 'instructions' => null, 'min_athletes' => 7, 'max_athletes' => 30, 'require_documents' => true])
            ->and($season->registrationOpen())->toBeTrue();
    });
});

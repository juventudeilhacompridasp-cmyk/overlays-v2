<?php

use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Teams\Mail\ManagerNotice;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\PersonDocument;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(fn (string $path) => "https://private.test/{$path}");
});

function pdfUpload(): UploadedFile
{
    return UploadedFile::fake()->create('atestado.pdf', 100, 'application/pdf');
}

describe('person documents in the panel', function () {
    it('stores the file privately with its validity and lists it with the status on the person screen', function () {
        $person = Person::factory()->create();

        $this->actingAs(panelUser(Role::Organizer))
            ->post(route('panel.people.documents.store', $person), ['type' => 'medical_certificate', 'expires_on' => now()->addDays(5)->toDateString(), 'file' => pdfUpload()])
            ->assertSessionHas('success');

        $document = PersonDocument::query()->firstOrFail();
        Storage::disk('s3-private')->assertExists($document->path);

        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.people.edit', $person))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/People/Form')
                ->has('documents', 1)
                ->where('documents.0.status', 'expiring')
                ->where('documents.0.type_label', 'Atestado médico')
                ->has('document_types', 3)
                ->missing('documents.0.path'));
    });

    it('opens the file through a signed link and deletes it with the file', function () {
        $person = Person::factory()->create();
        $organizer = panelUser(Role::Organizer);
        $this->actingAs($organizer)->post(route('panel.people.documents.store', $person), ['type' => 'insurance', 'expires_on' => now()->addYear()->toDateString(), 'file' => pdfUpload()]);
        $document = PersonDocument::query()->firstOrFail();

        $this->actingAs($organizer)->get(route('panel.people.documents.show', $document))->assertRedirect("https://private.test/{$document->path}");

        $this->actingAs($organizer)->delete(route('panel.people.documents.destroy', $document))->assertSessionHas('success');
        expect(PersonDocument::query()->count())->toBe(0);
        Storage::disk('s3-private')->assertMissing($document->path);
    });

    it('validates the type, the validity and the file', function () {
        $person = Person::factory()->create();

        $this->actingAs(panelUser(Role::Organizer))
            ->post(route('panel.people.documents.store', $person), ['type' => 'x', 'expires_on' => '', 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertSessionHasErrors(['type', 'expires_on', 'file']);
        expect(PersonDocument::query()->count())->toBe(0);
    });

    it('keeps the files closed to who only operates games or views', function () {
        $person = Person::factory()->create();
        $document = PersonDocument::factory()->for($person)->create();

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.people.documents.show', $document))->assertForbidden();
        $this->actingAs(panelUser(Role::Operator))->get(route('panel.documents.index'))->assertForbidden();
        $this->actingAs(panelUser(Role::Operator))->post(route('panel.people.documents.store', $person), ['type' => 'other', 'expires_on' => now()->addDay()->toDateString(), 'file' => pdfUpload()])->assertRedirect();
        $this->actingAs(panelUser(Role::Viewer))->delete(route('panel.people.documents.destroy', $document))->assertRedirect();

        expect(PersonDocument::query()->count())->toBe(1);
    });

    it('lists only expired and soon-to-expire documents, with the person teams', function () {
        $roster = RosterEntry::factory()->create();
        PersonDocument::factory()->create(['person_id' => $roster->person_id, 'expires_on' => now()->addDays(10)->toDateString()]);
        PersonDocument::factory()->create(['expires_on' => now()->subDays(3)->toDateString()]);
        PersonDocument::factory()->create(['expires_on' => now()->addDays(200)->toDateString()]);

        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Documents/Index')
                ->has('documents', 2)
                ->where('documents.0.status', 'expired')
                ->where('documents.1.status', 'expiring')
                ->where('documents.1.teams', [$roster->entry->team->name]));
    });

    it('purges the files when the person is deleted', function () {
        $person = Person::factory()->create();
        $organizer = panelUser(Role::Organizer);
        $this->actingAs($organizer)->post(route('panel.people.documents.store', $person), ['type' => 'other', 'expires_on' => now()->addYear()->toDateString(), 'file' => pdfUpload()]);
        $path = PersonDocument::query()->firstOrFail()->path;

        $this->actingAs($organizer)->delete(route('panel.people.destroy', $person))->assertRedirect();

        expect(PersonDocument::query()->count())->toBe(0);
        Storage::disk('s3-private')->assertMissing($path);
    });
});

describe('medical certificate rule', function () {
    function certificateGame(bool $required): array
    {
        $data = sheetGame();
        $season = $data['game']->division->season;
        $season->update(['rules' => [...$season->ruleSet()->toArray(), 'documents' => ['medical_required' => $required, 'warn_days' => 15]]]);

        return $data;
    }

    it('refuses athletes without a valid certificate on the game day and names them', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = certificateGame(true);
        PersonDocument::factory()->create(['person_id' => $hp[0]]);
        PersonDocument::factory()->create(['person_id' => $hp[1], 'expires_on' => now()->subDay()->toDateString()]);

        expect(fn () => app(SaveGameLineup::class)->handle($game, $home, array_slice($hp, 0, 11), array_slice($hp, 11)))
            ->toThrow(InvalidArgumentException::class, 'Sem atestado médico válido na data do jogo');
    });

    it('accepts the lineup when every athlete has a valid certificate, ignoring other document types', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = certificateGame(true);
        foreach ($hp as $personId) {
            PersonDocument::factory()->create(['person_id' => $personId]);
        }
        PersonDocument::factory()->create(['person_id' => $hp[0], 'type' => 'insurance', 'expires_on' => now()->subYear()->toDateString()]);

        app(SaveGameLineup::class)->handle($game, $home, array_slice($hp, 0, 11), array_slice($hp, 11));

        expect($game->lineups()->where('entry_id', $home->id)->count())->toBe(14);
    });

    it('does not check anything when the edition does not require the certificate', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = certificateGame(false);

        app(SaveGameLineup::class)->handle($game, $home, array_slice($hp, 0, 11), array_slice($hp, 11));

        expect($game->lineups()->where('entry_id', $home->id)->count())->toBe(14);
    });

    it('evaluates the certificate on the game date, not today', function () {
        ['game' => $game, 'home' => $home, 'hp' => $hp] = certificateGame(true);
        $game->update(['scheduled_at' => now()->addDays(20)]);
        foreach ($hp as $personId) {
            PersonDocument::factory()->create(['person_id' => $personId, 'expires_on' => now()->addDays(10)->toDateString()]);
        }

        expect(fn () => app(SaveGameLineup::class)->handle($game->fresh(), $home, array_slice($hp, 0, 11), array_slice($hp, 11)))
            ->toThrow(InvalidArgumentException::class, 'Sem atestado');
    });

    it('reads the rule back from the saved edition rules', function () {
        ['game' => $game] = certificateGame(true);

        expect($game->division->season->fresh()->ruleSet()->requireMedicalCertificate)->toBeTrue()
            ->and($game->division->season->fresh()->ruleSet()->documentWarnDays)->toBe(15);
    });
});

describe('manager reminders', function () {
    function rosterWithDocument(int $daysFromToday): array
    {
        $roster = RosterEntry::factory()->create();
        PersonDocument::factory()->create(['person_id' => $roster->person_id, 'expires_on' => now()->addDays($daysFromToday)->toDateString()]);

        return [$roster, managerFor($roster->entry->team)];
    }

    it('warns the manager once about documents that are about to expire', function () {
        Mail::fake();
        [, $manager] = rosterWithDocument(5);

        $this->artisan('documents:remind')->assertSuccessful();

        Mail::assertQueued(ManagerNotice::class, 1);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($manager->email) && str_contains($mail->subjectLine, 'perto de vencer'));

        $this->artisan('documents:remind')->assertSuccessful();
        Mail::assertQueued(ManagerNotice::class, 1);
    });

    it('warns about recently expired documents separately and ignores far or very old ones', function () {
        Mail::fake();
        [, $manager] = rosterWithDocument(-4);
        rosterWithDocument(120);
        rosterWithDocument(-90);

        $this->artisan('documents:remind');

        Mail::assertQueued(ManagerNotice::class, 1);
        Mail::assertQueued(ManagerNotice::class, fn (ManagerNotice $mail) => $mail->hasTo($manager->email) && str_contains($mail->subjectLine, 'vencidos'));
    });

    it('stays quiet when the organization turned the notice off or the player left the roster', function () {
        Mail::fake();
        [$roster] = rosterWithDocument(5);
        CurrentOrganization::get()->update(['settings' => ['communications' => ['manager_documents' => false]]]);

        $this->artisan('documents:remind');
        Mail::assertNothingQueued();

        CurrentOrganization::get()->update(['settings' => []]);
        $roster->update(['status' => 'removed']);

        $this->artisan('documents:remind');
        Mail::assertNothingQueued();
    });
});

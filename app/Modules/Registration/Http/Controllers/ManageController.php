<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Registration\Actions\ManageRegistrationDocuments;
use App\Modules\Registration\Actions\RemoveRegistrationPerson;
use App\Modules\Registration\Actions\SaveRegistrationPerson;
use App\Modules\Registration\Actions\SubmitRegistration;
use App\Modules\Registration\Enums\DocumentType;
use App\Modules\Registration\Http\Requests\DocumentUploadRequest;
use App\Modules\Registration\Http\Requests\PersonDataRequest;
use App\Modules\Registration\Http\Requests\TeamDataRequest;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationDocument;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Registration\Support\EnsureEditable;
use App\Modules\Teams\Enums\RosterRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Área da equipe (sem login): o token do link é o acesso ao pedido. Só o responsável deve ter esse link. */
class ManageController extends Controller
{
    public function show(Registration $registration, SubmitRegistration $submit): Response
    {
        $registration->load(['season.competition', 'division', 'people.documents']);
        $theme = PublicTheme::current();
        $settings = $registration->season->registrationSettings();

        return Inertia::render('Registration/Manage', [
            'theme' => $theme,
            'seo' => ['title' => "Minha inscrição · {$theme['name']}", 'description' => 'Acompanhe a inscrição da sua equipe.', 'url' => $registration->manageUrl(), 'image' => null],
            'registration' => [
                'status' => $registration->status->value,
                'status_label' => $registration->status->label(),
                'editable' => $registration->status->isEditable(),
                'review_note' => $registration->review_note,
                'team_name' => $registration->team_name,
                'team_short_name' => $registration->team_short_name,
                'team_city' => $registration->team_city,
                'contact_name' => $registration->contact_name,
                'contact_email' => $registration->contact_email,
                'contact_phone' => $registration->contact_phone,
                'competition' => $registration->season->competition->name,
                'season' => $registration->season->name,
                'division' => $registration->division->name,
            ],
            'people' => $registration->people->sortBy([fn ($a, $b) => ($a->role === RosterRole::Athlete ? 0 : 1) <=> ($b->role === RosterRole::Athlete ? 0 : 1), fn ($a, $b) => ($a->shirt_number ?? 999) <=> ($b->shirt_number ?? 999), fn ($a, $b) => $a->name <=> $b->name])->values()->map(fn (RegistrationPerson $person) => [
                'id' => $person->getKey(),
                'role' => $person->role->value,
                'role_label' => $person->role->label(),
                'name' => $person->name,
                'nickname' => $person->nickname,
                'birth_date' => $person->birth_date?->format('Y-m-d'),
                'has_document' => $person->document_hash !== null,
                'shirt_number' => $person->shirt_number,
                'minor' => $person->isMinor(),
                'guardian_name' => $person->guardian_name,
                'review_status' => $person->review_status->value,
                'review_status_label' => $person->review_status->label(),
                'review_note' => $person->review_note,
                'documents' => $person->documents->map(fn (RegistrationDocument $document) => [
                    'id' => $document->getKey(),
                    'type_label' => $document->type->label(),
                    'name' => $document->original_name,
                    'size_kb' => (int) ceil($document->size / 1024),
                ])->values(),
            ]),
            'problems' => $registration->status->isEditable() ? $submit->problems($registration) : [],
            'requirements' => ['min_athletes' => $settings['min_athletes'], 'max_athletes' => $settings['max_athletes'], 'require_documents' => $settings['require_documents'], 'instructions' => $settings['instructions'], 'deadline' => $settings['deadline']],
            'options' => [
                'roles' => array_map(fn (RosterRole $role) => ['value' => $role->value, 'label' => $role->label()], RosterRole::cases()),
                'document_types' => array_map(fn (DocumentType $type) => ['value' => $type->value, 'label' => $type->label()], DocumentType::cases()),
            ],
        ]);
    }

    public function updateTeam(TeamDataRequest $request, Registration $registration): RedirectResponse
    {
        return $this->attempt(function () use ($request, $registration): string {
            EnsureEditable::check($registration);
            $registration->update($request->validated());

            return 'Dados da equipe salvos.';
        });
    }

    public function storePerson(PersonDataRequest $request, Registration $registration, SaveRegistrationPerson $save): RedirectResponse
    {
        return $this->attempt(function () use ($request, $registration, $save): string {
            $person = $save->handle($registration, $request->validated());

            return "{$person->name} foi adicionado(a). Agora envie os documentos.";
        });
    }

    public function updatePerson(PersonDataRequest $request, Registration $registration, RegistrationPerson $person, SaveRegistrationPerson $save): RedirectResponse
    {
        $this->owns($registration, $person);

        return $this->attempt(function () use ($request, $registration, $person, $save): string {
            $save->handle($registration, $request->validated(), $person);

            return 'Dados salvos.';
        });
    }

    public function destroyPerson(Registration $registration, RegistrationPerson $person, RemoveRegistrationPerson $remove): RedirectResponse
    {
        $this->owns($registration, $person);

        return $this->attempt(function () use ($person, $remove): string {
            $remove->handle($person);

            return 'Removido(a) do pedido.';
        });
    }

    public function storeDocument(DocumentUploadRequest $request, Registration $registration, RegistrationPerson $person, ManageRegistrationDocuments $documents): RedirectResponse
    {
        $this->owns($registration, $person);

        return $this->attempt(function () use ($request, $person, $documents): string {
            $documents->attach($person, $request->file('file'), DocumentType::from($request->string('type')->toString()));

            return 'Documento enviado.';
        });
    }

    public function destroyDocument(Registration $registration, RegistrationPerson $person, RegistrationDocument $document, ManageRegistrationDocuments $documents): RedirectResponse
    {
        $this->owns($registration, $person);
        abort_unless($document->registration_person_id === $person->getKey(), 404);

        return $this->attempt(function () use ($document, $documents): string {
            $documents->remove($document);

            return 'Documento removido.';
        });
    }

    public function submit(Registration $registration, SubmitRegistration $submit): RedirectResponse
    {
        return $this->attempt(function () use ($registration, $submit): string {
            $submit->handle($registration);

            return 'Pedido enviado para análise. Avisaremos por e-mail quando houver uma decisão.';
        });
    }

    private function owns(Registration $registration, RegistrationPerson $person): void
    {
        abort_unless($person->registration_id === $registration->getKey(), 404);
    }

    /** @param  callable(): string  $action  devolve a mensagem de sucesso */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            return back()->with('success', $action());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}

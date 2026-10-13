<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Registration\Actions\ManageRegistrationDocuments;
use App\Modules\Registration\Actions\ReviewRegistration;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Enums\ReviewStatus;
use App\Modules\Registration\Mail\RegistrationMail;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\RegistrationDocument;
use App\Modules\Registration\Models\RegistrationPerson;
use App\Modules\Teams\Actions\InviteTeamManager;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Painel: análise dos pedidos de inscrição. Dados pessoais e documentos: só quem gerencia competições. */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $registrations = Registration::query()
            ->with(['season.competition', 'division'])
            ->withCount(['people', 'people as athletes_count' => fn ($query) => $query->where('role', RosterRole::Athlete)])
            ->when(is_string($status) && RegistrationStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->orderByRaw("case status when 'submitted' then 0 when 'returned' then 1 when 'draft' then 2 else 3 end")
            ->orderByDesc('submitted_at')->orderByDesc('created_at')
            ->paginate(20)->withQueryString()
            ->through(fn (Registration $registration) => [
                'id' => $registration->getKey(),
                'team_name' => $registration->team_name,
                'supplement' => $registration->isSupplement(),
                'competition' => $registration->season->competition->name,
                'season' => $registration->season->name,
                'division' => $registration->division->name,
                'status' => $registration->status->value,
                'status_label' => $registration->status->label(),
                'athletes_count' => $registration->athletes_count,
                'people_count' => $registration->people_count,
                'submitted_at' => $registration->submitted_at?->format('d/m/Y H:i'),
                'created_at' => $registration->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Panel/Registrations/Index', [
            'registrations' => $registrations,
            'filters' => ['status' => is_string($status) ? $status : ''],
            'statuses' => array_map(fn (RegistrationStatus $item) => ['value' => $item->value, 'label' => $item->label()], RegistrationStatus::cases()),
            'pending' => Registration::query()->where('status', RegistrationStatus::Submitted)->count(),
        ]);
    }

    public function show(Registration $registration): Response
    {
        $registration->load(['season.competition', 'division', 'people.documents']);
        $settings = $registration->season->registrationSettings();
        $sameName = Team::query()->whereRaw('lower(name) = ?', [mb_strtolower($registration->team_name)])->first();

        $people = $registration->people->sortBy([fn ($a, $b) => ($a->role === RosterRole::Athlete ? 0 : 1) <=> ($b->role === RosterRole::Athlete ? 0 : 1), fn ($a, $b) => ($a->shirt_number ?? 999) <=> ($b->shirt_number ?? 999), fn ($a, $b) => $a->name <=> $b->name])->values();

        return Inertia::render('Panel/Registrations/Show', [
            'registration' => [
                'id' => $registration->getKey(),
                'supplement' => $registration->isSupplement(),
                'team_id' => $registration->team_id,
                'status' => $registration->status->value,
                'status_label' => $registration->status->label(),
                'reviewable' => $registration->status === RegistrationStatus::Submitted,
                'review_note' => $registration->review_note,
                'team_name' => $registration->team_name,
                'team_short_name' => $registration->team_short_name,
                'team_city' => $registration->team_city,
                'contact_name' => $registration->contact_name,
                'contact_email' => $registration->contact_email,
                'contact_phone' => $registration->contact_phone,
                'consent_at' => $registration->consent_at?->format('d/m/Y H:i'),
                'consent_version' => $registration->consent_document_id !== null ? LegalDocument::query()->whereKey($registration->consent_document_id)->value('version') : null,
                'submitted_at' => $registration->submitted_at?->format('d/m/Y H:i'),
                'competition' => $registration->season->competition->name,
                'season' => $registration->season->name,
                'season_id' => $registration->season_id,
                'division' => $registration->division->name,
                'entry_id' => $registration->entry_id,
            ],
            'people' => $people->map(fn (RegistrationPerson $person) => [
                'id' => $person->getKey(),
                'role_label' => $person->role->label(),
                'is_athlete' => $person->role === RosterRole::Athlete,
                'name' => $person->name,
                'nickname' => $person->nickname,
                'birth_date' => $person->birth_date?->format('d/m/Y'),
                'age' => $person->birth_date?->age,
                'minor' => $person->isMinor(),
                'has_cpf' => $person->document_hash !== null,
                'shirt_number' => $person->shirt_number,
                'guardian_name' => $person->guardian_name,
                'review_status' => $person->review_status->value,
                'review_note' => $person->review_note,
                'documents' => $person->documents->map(fn (RegistrationDocument $document) => [
                    'id' => $document->getKey(),
                    'type_label' => $document->type->label(),
                    'name' => $document->original_name,
                    'size_kb' => (int) ceil($document->size / 1024),
                    'url' => route('panel.registrations.documents.show', $document->getKey()),
                ])->values(),
            ]),
            'requirements' => ['min_athletes' => $settings['min_athletes'], 'require_documents' => $settings['require_documents']],
            'teams' => Team::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'suggested_team_id' => $sameName?->getKey(),
        ]);
    }

    public function reviewPerson(Request $request, Registration $registration, RegistrationPerson $person, ReviewRegistration $review): RedirectResponse
    {
        abort_unless($person->registration_id === $registration->getKey(), 404);

        $validated = $request->validate([
            'review_status' => ['required', Rule::in([ReviewStatus::Approved->value, ReviewStatus::Rejected->value, ReviewStatus::Pending->value])],
            'review_note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(function () use ($review, $person, $validated): string {
            $review->reviewPerson($person, ReviewStatus::from($validated['review_status']), $validated['review_note'] ?? null);

            return 'Análise salva.';
        });
    }

    public function approve(Request $request, Registration $registration, ReviewRegistration $review): RedirectResponse
    {
        $validated = $request->validate(['team_id' => ['nullable', 'string', Rule::exists('teams', 'id')]]);

        return $this->attempt(function () use ($request, $registration, $review, $validated): string {
            $user = $request->user();
            abort_if($user === null, 403);
            $review->approve($registration, $user, $validated['team_id'] ?? null);
            Mail::to($registration->contact_email)->queue(RegistrationMail::approved($registration));

            return $registration->isSupplement() ? 'Reforços aprovados: as pessoas aprovadas entraram no elenco.' : 'Inscrição aprovada: equipe, inscrição e elenco criados.';
        });
    }

    /** Convida o responsável da inscrição aprovada como gestor da equipe (ação explícita do organizador; ADR 0003). */
    public function inviteManager(Request $request, Registration $registration, InviteTeamManager $invite): RedirectResponse
    {
        if ($registration->isSupplement() || $registration->status !== RegistrationStatus::Approved || $registration->team_id === null) {
            return back()->with('error', 'Só dá para convidar o responsável de uma inscrição aprovada.');
        }

        $team = Team::query()->findOrFail($registration->team_id);

        return $this->attempt(function () use ($request, $invite, $team, $registration): string {
            $invite->handle($team, $registration->contact_name, $registration->contact_email, $request->user());

            return "{$registration->contact_name} foi convidado(a) como gestor(a) de {$team->name}.";
        });
    }

    public function returnForChanges(Request $request, Registration $registration, ReviewRegistration $review): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:1000']], [], ['note' => 'mensagem']);

        return $this->attempt(function () use ($request, $registration, $review, $validated): string {
            $user = $request->user();
            abort_if($user === null, 403);
            $review->returnForChanges($registration, $validated['note'], $user);
            Mail::to($registration->contact_email)->queue(RegistrationMail::returned($registration));

            return 'Pedido devolvido para ajustes; a equipe foi avisada por e-mail.';
        });
    }

    public function reject(Request $request, Registration $registration, ReviewRegistration $review): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:1000']], [], ['note' => 'motivo']);

        return $this->attempt(function () use ($request, $registration, $review, $validated): string {
            $user = $request->user();
            abort_if($user === null, 403);
            $review->reject($registration, $validated['note'], $user);
            Mail::to($registration->contact_email)->queue(RegistrationMail::rejected($registration));

            return 'Pedido recusado; a equipe foi avisada por e-mail.';
        });
    }

    public function destroy(Registration $registration, ManageRegistrationDocuments $documents): RedirectResponse
    {
        if ($registration->status === RegistrationStatus::Approved) {
            return back()->with('error', 'Pedidos aprovados ficam como histórico da inscrição e não podem ser excluídos.');
        }

        $documents->purge($registration);
        $registration->delete();

        return to_route('panel.registrations.index')->with('success', 'Pedido excluído, com os documentos enviados.');
    }

    /** Abre o documento por URL temporária assinada (o arquivo nunca é público). */
    public function document(RegistrationDocument $document, ManageRegistrationDocuments $documents): RedirectResponse
    {
        $url = $documents->signedUrl($document);

        abort_if($url === null, 404);

        return redirect()->away($url);
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

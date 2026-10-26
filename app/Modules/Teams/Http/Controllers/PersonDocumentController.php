<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\ManagePersonDocuments;
use App\Modules\Teams\Enums\PersonDocumentType;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Http\Requests\PersonDocumentRequest;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\PersonDocument;
use App\Modules\Teams\Support\DocumentValidity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Documentos com validade de uma pessoa: enviar, abrir (URL assinada) e excluir. Só quem gerencia cadastros. */
class PersonDocumentController extends Controller
{
    private const LIST_DAYS = 30;

    /** Documentos vencidos ou que vencem em até 30 dias, para o organizador cobrar a renovação. */
    public function index(): Response
    {
        $today = now()->startOfDay();

        $rows = PersonDocument::query()
            ->whereDate('expires_on', '<=', $today->copy()->addDays(self::LIST_DAYS)->toDateString())
            ->with(['person.rosterEntries' => fn ($query) => $query->where('status', RosterStatus::Active)->with('entry.team')])
            ->orderBy('expires_on')
            ->limit(500)
            ->get()
            ->map(function (PersonDocument $document) use ($today): array {
                $status = DocumentValidity::status($document->expires_on, $today, self::LIST_DAYS);

                return [
                    'id' => $document->getKey(),
                    'person_id' => $document->person_id,
                    'person' => $document->person->name,
                    'type_label' => $document->type->label(),
                    'expires_on' => $document->expires_on->format('Y-m-d'),
                    'days_left' => DocumentValidity::daysLeft($document->expires_on, $today),
                    'status' => $status,
                    'status_label' => DocumentValidity::label($status),
                    'teams' => $document->person->rosterEntries->map(fn ($row) => $row->entry->team->name)->unique()->values()->all(),
                ];
            })->values();

        return Inertia::render('Panel/Documents/Index', ['documents' => $rows, 'window_days' => self::LIST_DAYS]);
    }

    public function store(PersonDocumentRequest $request, Person $person, ManagePersonDocuments $documents): RedirectResponse
    {
        try {
            $documents->attach($person, $request->file('file'), PersonDocumentType::from($request->string('type')->toString()), Carbon::parse($request->string('expires_on')->toString()));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Documento guardado.');
    }

    public function show(PersonDocument $document, ManagePersonDocuments $documents): RedirectResponse
    {
        $url = $documents->signedUrl($document);

        return $url === null ? back()->with('error', 'Não foi possível abrir o documento agora.') : redirect()->away($url);
    }

    public function destroy(PersonDocument $document, ManagePersonDocuments $documents): RedirectResponse
    {
        $documents->remove($document);

        return back()->with('success', 'Documento excluído.');
    }
}

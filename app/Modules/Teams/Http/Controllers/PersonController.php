<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Actions\ChangePersonPhoto;
use App\Modules\Teams\Actions\ManagePersonDocuments;
use App\Modules\Teams\Enums\PersonDocumentType;
use App\Modules\Teams\Http\Requests\PersonRequest;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\PersonDocument;
use App\Modules\Teams\Support\Cpf;
use App\Modules\Teams\Support\DocumentValidity;
use App\Support\Tables\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::fromRequest($request, ['name', 'age', 'rosters'], 'name', ['age', 'rosters', 'document']);
        $digits = Cpf::normalize($table->q);

        $query = Person::query()->withCount('rosterEntries');
        $table->search($query, ['name', 'nickname'], function ($builder) use ($digits): void {
            // Busca por CPF completo: compara o hash (o CPF não é guardado em claro).
            if (strlen($digits) === 11 && Cpf::isValid($digits)) {
                $builder->where('document_hash', Cpf::hash($digits));
            } else {
                $builder->whereRaw('1 = 0');
            }
        });
        $table->filter($query, [
            'age' => fn ($builder, string $value) => match ($value) {
                'minor' => $builder->whereDate('birth_date', '>', now()->subYears(18)->toDateString()),
                'adult' => $builder->whereDate('birth_date', '<=', now()->subYears(18)->toDateString()),
                'unknown' => $builder->whereNull('birth_date'),
                default => $builder,
            },
            'rosters' => fn ($builder, string $value) => match ($value) {
                'with' => $builder->has('rosterEntries'),
                'without' => $builder->doesntHave('rosterEntries'),
                default => $builder,
            },
            'document' => fn ($builder, string $value) => match ($value) {
                'informed' => $builder->whereNotNull('document_hash'),
                'missing' => $builder->whereNull('document_hash'),
                default => $builder,
            },
        ]);
        $table->sort($query, [
            'name' => ['column' => 'name', 'text' => true],
            // Idade crescente = nascimento decrescente; quem não tem data vai sempre para o fim.
            'age' => ['column' => 'birth_date', 'inverted' => true, 'nullable' => true],
            'rosters' => 'roster_entries_count',
        ]);

        $people = $query->paginate($table->perPage)
            ->withQueryString()
            ->through(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'nickname' => $person->nickname,
                'birth_date' => $person->birth_date?->format('Y-m-d'),
                'age' => $person->birth_date?->age,
                'has_document' => $person->document_hash !== null,
                'photo_url' => $person->photoUrl(96),
                'roster_entries_count' => $person->roster_entries_count,
            ]);

        return Inertia::render('Panel/People/Index', [
            'people' => $people,
            'filters' => $table->props(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Panel/People/Form', ['person' => null, 'documents' => [], 'document_types' => []]);
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        Person::query()->create($request->personData());

        return to_route('panel.people.index')->with('success', 'Pessoa cadastrada.');
    }

    public function edit(Person $person): Response
    {
        return Inertia::render('Panel/People/Form', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'nickname' => $person->nickname,
                'birth_date' => $person->birth_date?->format('Y-m-d'),
                'has_document' => $person->document_hash !== null,
                'photo_url' => $person->photoUrl(400),
            ],
            'documents' => $person->documents()->orderBy('expires_on')->get()->map(function (PersonDocument $document): array {
                $status = DocumentValidity::status($document->expires_on, now(), 15);

                return [
                    'id' => $document->getKey(),
                    'type_label' => $document->type->label(),
                    'original_name' => $document->original_name,
                    'expires_on' => $document->expires_on->format('Y-m-d'),
                    'days_left' => DocumentValidity::daysLeft($document->expires_on, now()),
                    'status' => $status,
                    'status_label' => DocumentValidity::label($status),
                ];
            })->values(),
            'document_types' => array_map(fn (PersonDocumentType $type) => ['value' => $type->value, 'label' => $type->label()], PersonDocumentType::cases()),
        ]);
    }

    public function update(PersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($request->personData());

        return to_route('panel.people.index')->with('success', 'Cadastro atualizado.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $hasHistory = GameEvent::query()
            ->where(fn ($query) => $query->where('person_id', $person->getKey())->orWhere('secondary_person_id', $person->getKey()))
            ->exists();

        if ($person->rosterEntries()->exists() || $hasHistory) {
            return back()->with('error', 'Esta pessoa está em elencos ou tem histórico de jogos. Remova dos elencos antes de excluir.');
        }

        app(ChangePersonPhoto::class)->handle($person, null);
        app(ManagePersonDocuments::class)->purge($person);
        $person->delete();

        return to_route('panel.people.index')->with('success', 'Pessoa excluída.');
    }
}

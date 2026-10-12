<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Http\Requests\PersonRequest;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $digits = Cpf::normalize($search);

        $people = Person::query()
            ->withCount('rosterEntries')
            ->when($search !== '', function ($query) use ($search, $digits) {
                $query->where(function ($inner) use ($search, $digits) {
                    $inner->where('name', 'like', "%{$search}%")->orWhere('nickname', 'like', "%{$search}%");

                    // Busca por CPF completo: compara o hash (o CPF não é guardado em claro).
                    if (strlen($digits) === 11 && Cpf::isValid($digits)) {
                        $inner->orWhere('document_hash', Cpf::hash($digits));
                    }
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'nickname' => $person->nickname,
                'birth_date' => $person->birth_date?->format('Y-m-d'),
                'age' => $person->birth_date?->age,
                'has_document' => $person->document_hash !== null,
                'roster_entries_count' => $person->roster_entries_count,
            ]);

        return Inertia::render('Panel/People/Index', [
            'people' => $people,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Panel/People/Form', ['person' => null]);
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
            ],
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

        $person->delete();

        return to_route('panel.people.index')->with('success', 'Pessoa excluída.');
    }
}

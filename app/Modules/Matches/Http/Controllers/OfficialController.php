<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Http\Requests\OfficialRequest;
use App\Modules\Matches\Models\Official;
use App\Support\Tables\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Cadastro de árbitros, assistentes e mesários (sem CPF nem documentos). Acesso de quem gerencia competições. */
class OfficialController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::fromRequest($request, ['name', 'email', 'active', 'games'], 'name', ['active']);

        $query = Official::query()->withCount('assignments');
        $table->search($query, ['name', 'email', 'phone']);
        $table->filter($query, [
            'active' => fn ($builder, string $value) => match ($value) {
                'yes' => $builder->where('active', true),
                'no' => $builder->where('active', false),
                default => $builder,
            },
        ]);
        $table->sort($query, [
            'name' => ['column' => 'name', 'text' => true],
            'email' => ['column' => 'email', 'text' => true, 'nullable' => true],
            'active' => 'active',
            'games' => 'assignments_count',
        ]);

        return Inertia::render('Panel/Officials/Index', [
            'officials' => $query->paginate($table->perPage)->withQueryString(),
            'filters' => $table->props(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Panel/Officials/Form', ['official' => null]);
    }

    public function store(OfficialRequest $request): RedirectResponse
    {
        Official::query()->create($request->validated());

        return to_route('panel.officials.index')->with('success', 'Cadastrado. Já pode entrar na escala dos jogos.');
    }

    public function edit(Official $official): Response
    {
        return Inertia::render('Panel/Officials/Form', ['official' => $official->only(['id', 'name', 'email', 'phone', 'active'])]);
    }

    public function update(OfficialRequest $request, Official $official): RedirectResponse
    {
        $official->update($request->validated());

        return to_route('panel.officials.index')->with('success', 'Cadastro atualizado.');
    }

    public function destroy(Official $official): RedirectResponse
    {
        $official->delete();

        return to_route('panel.officials.index')->with('success', 'Excluído. Ele saiu da escala dos jogos.');
    }
}

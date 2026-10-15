<?php

namespace App\Modules\Help\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Help\Models\HelpNote;
use App\Modules\Help\Models\HelpQuestion;
use App\Modules\Help\Support\HelpAiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel > Notas da ajuda (só administrador): procedimentos internos da organização que aparecem na central de ajuda dela,
 * para a equipe do painel ou para os gestores de equipe. Texto simples (o mesmo formato dos artigos), nunca HTML.
 */
class HelpNoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Panel/HelpNotes/Index', [
            'notes' => HelpNote::query()->orderBy('created_at')->get()->map(fn (HelpNote $note) => ['id' => $note->id, 'audience' => $note->audience, 'title' => $note->title, 'body' => $note->body])->values(),
            'audiences' => collect(HelpNote::AUDIENCES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'limit' => HelpNote::LIMIT,
            // Perguntas à ajuda com IA que ficaram sem resposta: o que falta na documentação (sem e-mails nem números longos).
            'gaps' => HelpAiSettings::released() ? HelpQuestion::query()->where('answered', false)->whereNotNull('question')->latest('created_at')->limit(20)->get()->map(fn (HelpQuestion $item) => ['id' => $item->getKey(), 'question' => $item->question, 'at' => $item->created_at?->toIso8601String()])->values() : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (HelpNote::query()->count() >= HelpNote::LIMIT) {
            return back()->with('error', 'Limite de '.HelpNote::LIMIT.' notas atingido. Exclua uma nota para criar outra.');
        }

        HelpNote::query()->create($this->validated($request) + ['created_by' => $request->user()?->getAuthIdentifier()]);

        return back()->with('success', 'Nota criada.');
    }

    public function update(Request $request, HelpNote $note): RedirectResponse
    {
        $note->update($this->validated($request));

        return back()->with('success', 'Nota atualizada.');
    }

    public function destroy(HelpNote $note): RedirectResponse
    {
        $note->delete();

        return back()->with('success', 'Nota excluída.');
    }

    /**
     * @return array{audience: string, title: string, body: string}
     */
    private function validated(Request $request): array
    {
        /** @var array{audience: string, title: string, body: string} $validated */
        $validated = $request->validate([
            'audience' => ['required', Rule::in(array_keys(HelpNote::AUDIENCES))],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], ['audience' => 'público', 'title' => 'título', 'body' => 'texto']);

        return ['audience' => $validated['audience'], 'title' => trim($validated['title']), 'body' => trim(str_replace(["\r\n", "\r"], "\n", $validated['body']))];
    }
}

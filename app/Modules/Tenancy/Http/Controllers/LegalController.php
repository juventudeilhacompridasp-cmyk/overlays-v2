<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\PublishLegalDocument;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\LegalType;
use App\Modules\Tenancy\Models\LegalAcceptance;
use App\Modules\Tenancy\Models\LegalDocument;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Support\LegalDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Painel (administradores): termos de uso e política de privacidade da organização, com histórico de versões e aceites. */
class LegalController extends Controller
{
    public function edit(): Response
    {
        $members = Membership::query()->count();

        $documents = collect(LegalType::cases())->map(function (LegalType $type) use ($members) {
            $view = LegalDocuments::view($type);

            return [
                ...$view,
                'default_body' => LegalDocuments::defaultBody($type),
                'public_url' => url($type->path()),
                'accepted' => $view['document_id'] !== null ? LegalAcceptance::query()->where('legal_document_id', $view['document_id'])->count() : null,
                'members' => $members,
                'history' => LegalDocument::query()->where('type', $type->value)->orderByDesc('version')->get()->map(fn (LegalDocument $document) => [
                    'version' => $document->version,
                    'published_at' => $document->published_at->format('d/m/Y H:i'),
                    'accepted' => LegalAcceptance::query()->where('legal_document_id', $document->getKey())->count(),
                ])->values(),
            ];
        })->values();

        return Inertia::render('Panel/Legal/Edit', ['documents' => $documents, 'settings' => LegalDocuments::settings()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'controller_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:190'],
        ], [], ['controller_name' => 'razão social / responsável', 'contact_email' => 'e-mail de contato']);

        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        $settings = $organization->settings ?? [];
        $settings['legal'] = ['controller_name' => $validated['controller_name'] ?? null, 'contact_email' => $validated['contact_email'] ?? null];
        $organization->update(['settings' => $settings]);

        return back()->with('success', 'Dados de contato salvos. Eles entram nos textos-modelo ainda não publicados.');
    }

    public function publish(Request $request, PublishLegalDocument $publish): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(LegalType::class)],
            'body' => ['required', 'string', 'max:60000'],
        ], [], ['body' => 'texto']);

        try {
            $document = $publish->handle(LegalType::from($validated['type']), $validated['body'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "{$document->type->label()}: versão {$document->version} publicada. Os usuários do painel vão precisar aceitá-la no próximo acesso.");
    }
}

<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\AcceptLegalDocuments;
use App\Modules\Tenancy\Models\LegalDocument;
use App\Modules\Tenancy\Support\LegalDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Tela de aceite dos termos/privacidade publicados, exigida antes de usar o painel. */
class LegalAcceptController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $pending = LegalDocuments::pendingFor($request->user());

        if ($pending->isEmpty()) {
            return redirect()->route('panel.dashboard');
        }

        return Inertia::render('Legal/Accept', [
            'documents' => $pending->map(fn (LegalDocument $document) => [
                'title' => $document->type->label(),
                'version' => $document->version,
                'published_at' => $document->published_at->format('d/m/Y'),
                'body' => $document->body,
            ])->values(),
        ]);
    }

    public function store(Request $request, AcceptLegalDocuments $accept): RedirectResponse
    {
        $request->validate(['accept' => ['accepted']], ['accept.accepted' => 'É preciso aceitar para continuar.']);

        $accept->handle($request->user());

        return redirect()->route('panel.dashboard');
    }
}

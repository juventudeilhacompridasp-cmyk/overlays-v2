<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\DocumentAccess;
use App\Modules\Tenancy\Support\DocumentAccessLog;
use Inertia\Inertia;
use Inertia\Response;

/** Painel > Acesso a documentos (só quem gerencia usuários): quem abriu documentos pessoais, os 300 acessos mais recentes. */
class DocumentAccessController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Panel/DocumentAccess/Index', [
            'entries' => DocumentAccess::query()->latest('created_at')->latest('id')->limit(300)->get()->map(fn (DocumentAccess $entry) => [
                'id' => $entry->getKey(),
                'at' => $entry->created_at->toIso8601String(),
                'actor' => $entry->actor_name,
                'kind' => $entry->kind,
                'kind_label' => DocumentAccessLog::KINDS[$entry->kind] ?? $entry->kind,
                'label' => $entry->label,
            ])->values(),
        ]);
    }
}

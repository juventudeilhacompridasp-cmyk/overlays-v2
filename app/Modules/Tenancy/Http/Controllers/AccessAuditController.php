<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Models\AccessAudit;
use Inertia\Inertia;
use Inertia\Response;

/** Painel > Auditoria de acesso (só quem gerencia usuários): as últimas mudanças de perfil, papel e exceções (ADR 0005). */
class AccessAuditController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Panel/AccessAudit/Index', [
            'entries' => AccessAudit::query()->with('actor:id,name')->latest('created_at')->latest('id')->limit(200)->get()->map(fn (AccessAudit $entry) => [
                'id' => $entry->getKey(),
                'at' => $entry->created_at->toIso8601String(),
                'actor' => $entry->actor instanceof User ? $entry->actor->name : 'Sistema',
                'action' => $entry->action,
                'subject' => $entry->subject,
                'summary' => $entry->summary,
            ])->values(),
        ]);
    }
}

<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\Lead;
use App\Modules\Marketing\Support\LandingContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Contatos da landing page: só o Super Admin (`platform.admin`, 404 para os demais). */
class PlatformLeadController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $query = Lead::query()->orderByRaw("case status when 'new' then 0 when 'contacted' then 1 else 2 end")->orderByDesc('created_at');

        if (is_string($status) && array_key_exists($status, Lead::STATUSES)) {
            $query->where('status', $status);
        }

        return Inertia::render('Platform/Leads/Index', [
            'leads' => $query->limit(200)->get()->map(fn (Lead $lead) => [
                'id' => $lead->getKey(),
                'name' => $lead->name,
                'email' => $lead->email,
                'organization_name' => $lead->organization_name,
                'interest' => LandingContent::INTERESTS[$lead->interest ?? ''] ?? null,
                'message' => $lead->message,
                'status' => $lead->status,
                'created_at' => $lead->created_at?->copy()->utc()->toIso8601String(),
            ])->all(),
            'statuses' => array_map(fn (string $key, string $label) => ['value' => $key, 'label' => $label], array_keys(Lead::STATUSES), Lead::STATUSES),
            'filter' => is_string($status) && array_key_exists($status, Lead::STATUSES) ? $status : null,
            'counts' => Lead::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($total) => (int) $total)->all(),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(Lead::STATUSES))]])['status'];
        $lead->update(['status' => $status, 'handled_at' => $status === 'new' ? null : ($lead->handled_at ?? now())]);

        return back()->with('success', 'Contato atualizado.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        return back()->with('success', 'Contato removido.');
    }
}

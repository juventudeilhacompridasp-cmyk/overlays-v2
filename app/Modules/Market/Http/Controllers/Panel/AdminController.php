<?php

namespace App\Modules\Market\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\Sport;
use App\Modules\Market\Actions\ModerateMarket;
use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Enums\ReportStatus;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Models\MarketReport;
use App\Modules\Market\Support\MarketSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Painel > Mercado (`manage-market`, ADR 0008 parte 5): denúncias, perfis e a cota diária de pedidos. O organizador NÃO
 * vê contato nem data de nascimento do atleta; suspender tira o perfil da busca e mostra o motivo ao atleta.
 */
class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $status = ProfileStatus::tryFrom((string) $request->query('status'));

        $profiles = MarketProfile::query()->with('user:id,name')
            ->withCount(['reports as open_reports_count' => fn ($query) => $query->where('status', ReportStatus::Open->value)])
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->latest()->paginate(15)->withQueryString()
            ->through(fn (MarketProfile $profile) => [
                'id' => $profile->getKey(),
                'name' => $this->name($profile),
                'sport' => $profile->sport !== null ? Sport::tryFrom($profile->sport)?->label() : null,
                'city' => $profile->city,
                'status' => $profile->status->value,
                'status_label' => $profile->status->label(),
                'suspended_reason' => $profile->suspended_reason,
                'open_reports' => $profile->open_reports_count,
                'published_at' => $profile->published_at?->format('d/m/Y'),
            ]);

        $reports = MarketReport::query()->with(['profile.user:id,name', 'team:id,name', 'reporter:id,name'])
            ->orderByRaw("case status when 'open' then 0 else 1 end")->latest()->limit(40)->get()
            ->map(fn (MarketReport $report) => [
                'id' => $report->getKey(),
                'kind' => $report->reporter_kind,
                'reporter' => $report->reporter_kind === MarketReport::ATHLETE ? $this->name($report->profile) : $report->reporter->name,
                'about' => $report->reporter_kind === MarketReport::ATHLETE ? 'Equipe '.($report->team?->name ?? '—') : 'Jogador '.$this->name($report->profile),
                'profile_id' => $report->profile_id,
                'profile_status' => $report->profile->status->value,
                'reason' => $report->reason,
                'status' => $report->status->value,
                'status_label' => $report->status->label(),
                'note' => $report->note,
                'created_at' => $report->created_at->format('d/m/Y'),
            ])->values();

        return Inertia::render('Panel/Market/Index', [
            'reports' => $reports,
            'profiles' => $profiles,
            'status' => $status?->value,
            'statuses' => collect(ProfileStatus::cases())->map(fn (ProfileStatus $item) => ['value' => $item->value, 'label' => $item->label()])->values(),
            'totals' => [
                'published' => MarketProfile::query()->where('status', ProfileStatus::Published->value)->count(),
                'open_reports' => MarketReport::query()->where('status', ReportStatus::Open->value)->count(),
                'pending_requests' => ContactRequest::query()->where('status', 'pending')->where('expires_at', '>', now())->count(),
            ],
            'settings' => ['daily_requests' => MarketSettings::dailyRequests()],
        ]);
    }

    public function suspend(Request $request, MarketProfile $profile, ModerateMarket $moderate): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], [], ['reason' => 'motivo']);

        $moderate->suspend($profile, trim($data['reason']));

        return back()->with('success', 'Perfil suspenso: saiu da busca e o atleta vê o motivo.');
    }

    public function restore(MarketProfile $profile, ModerateMarket $moderate): RedirectResponse
    {
        try {
            $moderate->restore($profile);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Perfil reativado como pausado: o atleta decide quando publicar de novo.');
    }

    public function resolve(Request $request, MarketReport $report, ModerateMarket $moderate): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in([ReportStatus::Actioned->value, ReportStatus::Dismissed->value])],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['outcome' => 'resultado', 'note' => 'observação']);

        $moderate->resolve($report, $request->user(), ReportStatus::from($data['outcome']), $data['note'] ?? null);

        return back()->with('success', 'Denúncia encerrada.');
    }

    public function settings(Request $request, ModerateMarket $moderate): RedirectResponse
    {
        $data = $request->validate(['daily_requests' => ['required', 'integer', 'between:1,100']], [], ['daily_requests' => 'cota diária']);

        $moderate->dailyRequests((int) $data['daily_requests']);

        return back()->with('success', 'Cota diária salva.');
    }

    private function name(MarketProfile $profile): string
    {
        return filled($profile->display_name) ? $profile->display_name : $profile->user->name;
    }
}

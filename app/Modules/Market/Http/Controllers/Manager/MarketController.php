<?php

namespace App\Modules\Market\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\Sport;
use App\Modules\Market\Enums\RequestStatus;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketBlock;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Support\MarketCard;
use App\Modules\Market\Support\MarketOptions;
use App\Modules\Market\Support\MarketSearch;
use App\Modules\Market\Support\MarketSettings;
use App\Modules\Teams\Support\ManagedTeams;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Busca do gestor de equipe no Mercado da organização (ADR 0008, parte 2): cartões sem contato. */
class MarketController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = MarketSearch::filters($request->query());

        $teams = ManagedTeams::teams($request->user())->orderBy('name')->get(['id', 'name']);
        $teamIds = $teams->pluck('id')->all();

        // O atleta que bloqueou TODAS as equipes do gestor some da busca dele (o bloqueio não é revelado).
        $query = MarketSearch::query($filters);

        if ($teamIds !== []) {
            $query->whereNotIn('id', MarketBlock::query()->select('profile_id')->whereIn('team_id', $teamIds)->groupBy('profile_id')->havingRaw('count(distinct team_id) >= ?', [count($teamIds)]));
        }

        $page = $query->paginate(12)->withQueryString();

        $requests = ContactRequest::query()->with('team:id,name')
            ->whereIn('team_id', $teamIds)->whereIn('profile_id', $page->pluck('id'))->latest()->get()->groupBy('profile_id');
        $blocked = MarketBlock::query()->whereIn('team_id', $teamIds)->whereIn('profile_id', $page->pluck('id'))->get()->groupBy('profile_id');

        $profiles = $page->through(function (MarketProfile $profile) use ($requests, $blocked, $teams) {
            $mine = $requests->get($profile->getKey(), collect());
            $blockedTeams = $blocked->get($profile->getKey(), collect())->pluck('team_id')->all();
            $busy = $mine->filter(fn (ContactRequest $item) => $item->isOpen() || in_array($item->status, [RequestStatus::Accepted, RequestStatus::Declined], true))->pluck('team_id')->all();

            return [
                ...MarketCard::of($profile),
                'teams_available' => $teams->reject(fn ($team) => in_array($team->getKey(), [...$blockedTeams, ...$busy], true))
                    ->map(fn ($team) => ['id' => $team->getKey(), 'name' => $team->name])->values(),
                'requests' => $mine->map(fn (ContactRequest $item) => MarketCard::request($item, $profile))->values(),
            ];
        });

        return Inertia::render('Manager/Market/Index', [
            'profiles' => $profiles,
            'filters' => $filters,
            'daily_limit' => MarketSettings::dailyRequests(),
            // Todos os pedidos das equipes do gestor (inclusive de perfis que saíram da busca): o contato aceito fica aqui.
            'my_requests' => ContactRequest::query()->with(['team:id,name', 'profile.user:id,name'])
                ->whereIn('team_id', $teamIds)->latest()->limit(30)->get()
                ->map(fn (ContactRequest $item) => [
                    ...MarketCard::request($item, $item->profile),
                    'name' => filled($item->profile->display_name) ? $item->profile->display_name : $item->profile->user->name,
                ])->values(),
            'options' => [
                'sports' => collect(Sport::cases())->map(fn (Sport $sport) => [
                    'value' => $sport->value,
                    'label' => $sport->label(),
                    'positions' => MarketOptions::list($sport->profile()->positions),
                ])->values(),
                'days' => MarketOptions::list(MarketOptions::DAYS),
                'periods' => MarketOptions::list(MarketOptions::PERIODS),
                'levels' => MarketOptions::list(MarketOptions::LEVELS),
            ],
        ]);
    }
}

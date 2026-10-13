<?php

namespace App\Modules\Market\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\Sport;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Support\MarketCard;
use App\Modules\Market\Support\MarketOptions;
use App\Modules\Market\Support\MarketSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Busca do gestor de equipe no Mercado da organização (ADR 0008, parte 2): cartões sem contato. */
class MarketController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = MarketSearch::filters($request->query());

        $profiles = MarketSearch::query($filters)->paginate(12)->withQueryString()
            ->through(fn (MarketProfile $profile) => MarketCard::of($profile));

        return Inertia::render('Manager/Market/Index', [
            'profiles' => $profiles,
            'filters' => $filters,
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

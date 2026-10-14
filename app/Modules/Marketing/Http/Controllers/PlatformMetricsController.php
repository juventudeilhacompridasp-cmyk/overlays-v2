<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Support\LandingMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Medição anônima da landing page: só o Super Admin (`platform.admin`, 404 para os demais). */
class PlatformMetricsController extends Controller
{
    public const PERIODS = [7, 30, 90];

    public function index(Request $request): Response
    {
        $days = (int) $request->query('dias', 30);
        $days = in_array($days, self::PERIODS, true) ? $days : 30;

        return Inertia::render('Platform/Metrics/Index', [
            'enabled' => LandingMetrics::enabled(),
            'periods' => self::PERIODS,
            'summary' => LandingMetrics::summary($days),
        ]);
    }
}

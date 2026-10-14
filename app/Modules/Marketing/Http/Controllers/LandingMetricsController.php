<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Support\LandingMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Recebe os eventos anônimos da landing page (só com consentimento no navegador). Responde 404 com a medição desligada. */
class LandingMetricsController extends Controller
{
    public function store(Request $request): Response
    {
        abort_unless(LandingMetrics::enabled(), 404);

        $data = $request->validate([
            'events' => ['required', 'array', 'max:'.LandingMetrics::MAX_EVENTS],
            'events.*.metric' => ['required', 'string', 'max:20'],
            'events.*.dimension' => ['nullable', 'string', 'max:30'],
        ]);

        LandingMetrics::record($data['events']);

        return response()->noContent();
    }
}

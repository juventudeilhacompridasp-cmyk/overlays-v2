<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Actions\StoreLead;
use App\Modules\Marketing\Http\Requests\StoreLeadRequest;
use App\Modules\Marketing\Support\LandingContent;
use App\Modules\Marketing\Support\LandingMetrics;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Landing page do produto (docs/14): pública, sem organização no conteúdo. */
class LandingController extends Controller
{
    public function show(): Response
    {
        $url = route('marketing.landing');
        $content = LandingContent::build();

        return Inertia::render('Marketing/Landing', [
            'content' => $content,
            'brand' => (string) config('app.name'),
            'analytics' => LandingMetrics::enabled(),
            'links' => ['privacy' => route('portal.privacy'), 'terms' => route('portal.terms'), 'status' => route('status.index'), 'login' => route('login')],
            'seo' => [
                'title' => config('app.name').' · Gestão de competições com overlays ao vivo para OBS',
                'description' => $content['hero']['subtitle'],
                'url' => $url,
                'image' => null,
                'json_ld' => LandingContent::jsonLd($url),
            ],
        ]);
    }

    public function contact(StoreLeadRequest $request, StoreLead $leads): RedirectResponse
    {
        $leads->handle($request->safe()->only(['name', 'email', 'organization_name', 'interest', 'message']));

        return redirect()->to(route('marketing.landing').'#contato')->with('success', 'Mensagem enviada! Respondemos pelo e-mail informado em até 2 dias úteis.');
    }
}

<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Registration\Actions\StartRegistration;
use App\Modules\Registration\Http\Requests\ApplyRequest;
use App\Modules\Registration\Mail\RegistrationMail;
use App\Modules\Tenancy\Support\CommunicationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Formulário público para uma equipe pedir inscrição numa edição com inscrições abertas. */
class ApplyController extends Controller
{
    public function show(Competition $competition, Season $season): Response
    {
        $this->ensurePublic($competition, $season);

        $theme = PublicTheme::current();
        $settings = $season->registrationSettings();

        return Inertia::render('Registration/Apply', [
            'theme' => $theme,
            'seo' => [
                'title' => "Inscrição · {$competition->name} {$season->name} · {$theme['name']}",
                'description' => "Inscreva sua equipe em {$competition->name} {$season->name}.",
                'url' => route('registration.apply', [$competition->slug, $season->getKey()]),
                'image' => null,
            ],
            'competition' => ['name' => $competition->name, 'slug' => $competition->slug],
            'season' => ['id' => $season->getKey(), 'name' => $season->name, 'status_label' => $season->status->label()],
            'open' => $season->registrationOpen(),
            'deadline' => $settings['deadline'],
            'instructions' => $settings['instructions'],
            'requirements' => ['min_athletes' => $settings['min_athletes'], 'max_athletes' => $settings['max_athletes'], 'require_documents' => $settings['require_documents']],
            'divisions' => $season->divisions()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(ApplyRequest $request, Competition $competition, Season $season, StartRegistration $start): RedirectResponse
    {
        $this->ensurePublic($competition, $season);

        try {
            $registration = $start->handle($season, $request->safe()->except('consent'));
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        if (CommunicationSettings::enabled('registration_opened')) {
            Mail::to($registration->contact_email)->queue(RegistrationMail::opened($registration));
        }

        return redirect()->to($registration->manageUrl())->with('success', 'Pedido criado! Enviamos o link pessoal para o seu e-mail; guarde-o para continuar depois.');
    }

    private function ensurePublic(Competition $competition, Season $season): void
    {
        abort_unless($season->competition_id === $competition->getKey() && $season->isPublished(), 404);
    }
}

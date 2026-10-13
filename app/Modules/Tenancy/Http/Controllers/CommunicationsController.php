<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Support\CommunicationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Painel > Comunicações (só administrador): quais e-mails e avisos a organização envia e quando. */
class CommunicationsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Panel/Communications/Edit', ['settings' => CommunicationSettings::all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];

        foreach (array_keys(CommunicationSettings::SWITCHES) as $key) {
            $rules[$key] = ['required', 'boolean'];
        }

        $validated = $request->validate($rules + [
            'reminder_first_minutes' => ['required', 'integer', 'between:30,10080'],
            'reminder_second_minutes' => ['required', 'integer', 'between:15,10080', 'lt:reminder_first_minutes'],
        ], [], [
            'reminder_first_minutes' => 'primeiro lembrete',
            'reminder_second_minutes' => 'segundo lembrete',
        ]);

        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        $settings = $organization->settings ?? [];
        $settings['communications'] = $validated;
        $organization->update(['settings' => $settings]);

        return back()->with('success', 'Comunicações atualizadas.');
    }
}

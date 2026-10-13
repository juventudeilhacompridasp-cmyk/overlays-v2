<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Registration\Actions\StartSupplement;
use App\Modules\Teams\Models\RosterChange;
use App\Modules\Teams\Support\ManagedTeams;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Abre (ou retoma) o pedido de reforço da inscrição do gestor e o leva ao formulário do pedido (ADR 0003). */
class SupplementController extends Controller
{
    public function store(Request $request, Entry $entry, StartSupplement $start): RedirectResponse
    {
        ManagedTeams::authorizeEntry($request->user(), $entry);

        try {
            $registration = $start->handle($entry, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($registration->wasRecentlyCreated) {
            RosterChange::record($entry->getKey(), $entry->organization_id, RosterChange::SUPPLEMENT_REQUESTED, null, $request->user()->getKey());
        }

        return redirect()->to($registration->manageUrl());
    }
}

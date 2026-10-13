<?php

namespace App\Modules\Market\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Market\Actions\FileMarketReport;
use App\Modules\Market\Actions\StartMarketSupplement;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Teams\Support\ManagedTeams;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Do contato aceito ao pedido de reforço, e denúncia de perfil (ADR 0008, parte 5). */
class HiringController extends Controller
{
    public function supplement(Request $request, ContactRequest $contactRequest, StartMarketSupplement $start): RedirectResponse
    {
        ManagedTeams::authorizeTeam($request->user(), $contactRequest->team);

        $data = $request->validate(['entry_id' => ['required', 'string']], [], ['entry_id' => 'inscrição']);
        $entry = Entry::query()->findOrFail($data['entry_id']);
        ManagedTeams::authorizeEntry($request->user(), $entry);

        try {
            $registration = $start->handle($request->user(), $contactRequest, $entry);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->to($registration->manageUrl());
    }

    public function report(Request $request, MarketProfile $profile, FileMarketReport $reports): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']], [], ['reason' => 'motivo']);

        try {
            $reports->byManager($request->user(), $profile, trim($data['reason']));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Denúncia enviada à organização.');
    }
}

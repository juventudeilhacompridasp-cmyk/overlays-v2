<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Models\TeamManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Liga ou desliga os avisos por e-mail do gestor (vale para todas as equipes dele). */
class NoticeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['notify' => ['required', 'boolean']]);

        TeamManager::query()->where('user_id', $request->user()->getKey())->update(['notify' => (bool) $data['notify']]);

        return back()->with('success', $data['notify'] ? 'Avisos por e-mail ligados.' : 'Avisos por e-mail desligados.');
    }
}

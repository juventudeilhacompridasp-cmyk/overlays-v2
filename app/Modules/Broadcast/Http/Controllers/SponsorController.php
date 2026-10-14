<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\SaveOverlaySponsor;
use App\Modules\Broadcast\Models\OverlaySponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Patrocinadores dos overlays (Painel > Transmissão): criar, editar e excluir é de quem gerencia competições. */
class SponsorController extends Controller
{
    public function store(Request $request, SaveOverlaySponsor $save): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['name' => 'nome', 'logo' => 'logotipo']);

        try {
            $save->handle(null, trim($data['name']), true, $request->file('logo'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['logo' => $exception->getMessage()]);
        }

        return back()->with('success', 'Patrocinador cadastrado.');
    }

    public function update(Request $request, OverlaySponsor $sponsor, SaveOverlaySponsor $save): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'active' => ['sometimes', 'boolean'],
            'logo' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['name' => 'nome', 'logo' => 'logotipo']);

        $save->handle($sponsor, isset($data['name']) ? trim($data['name']) : $sponsor->name, (bool) ($data['active'] ?? $sponsor->active), $request->file('logo'));

        return back()->with('success', 'Patrocinador atualizado.');
    }

    public function destroy(OverlaySponsor $sponsor, SaveOverlaySponsor $save): RedirectResponse
    {
        $save->delete($sponsor);

        return back()->with('success', 'Patrocinador excluído.');
    }
}

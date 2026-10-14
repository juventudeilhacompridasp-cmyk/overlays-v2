<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\SaveOverlayFont;
use App\Modules\Broadcast\Models\OverlayFont;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Fontes próprias dos overlays (Painel > Transmissão): enviar e excluir é de quem gerencia competições. */
class FontController extends Controller
{
    public function store(Request $request, SaveOverlayFont $save): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'font' => ['required', 'file', 'max:2048'],
        ], [], ['name' => 'nome', 'font' => 'arquivo da fonte']);

        try {
            $save->handle($request->file('font'), trim($data['name']));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['font' => $exception->getMessage()]);
        }

        return back()->with('success', 'Fonte enviada.');
    }

    public function destroy(OverlayFont $font, SaveOverlayFont $save): RedirectResponse
    {
        $save->delete($font);

        return back()->with('success', 'Fonte excluída.');
    }
}

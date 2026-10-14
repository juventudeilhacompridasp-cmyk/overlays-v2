<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\SaveOverlayStyleModel;
use App\Modules\Broadcast\Models\OverlayStyleModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** "Meus modelos" dos overlays de campeonato: salvar, importar e excluir é de quem gerencia competições. */
class StyleModelController extends Controller
{
    public function store(Request $request, SaveOverlayStyleModel $save): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'options' => ['required', 'array'],
        ], [], ['name' => 'nome', 'options' => 'estilo']);

        try {
            $save->handle($data['name'], $data['options']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Modelo salvo.');
    }

    /** Importa o arquivo exportado (`juventude-obs-models`): nomes repetidos são atualizados; o que não for válido é ignorado. */
    public function import(Request $request, SaveOverlayStyleModel $save): RedirectResponse
    {
        $data = $request->validate([
            'models' => ['required', 'array', 'max:'.OverlayStyleModel::LIMIT],
            'models.*.name' => ['required', 'string', 'max:40'],
            'models.*.options' => ['required', 'array'],
        ], [], ['models' => 'modelos']);

        $saved = 0;

        foreach ($data['models'] as $model) {
            try {
                $save->handle($model['name'], $model['options']);
                $saved++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return back()->with($saved > 0 ? 'success' : 'error', $saved > 0 ? "{$saved} modelo(s) importado(s)." : 'Nenhum modelo válido no arquivo.');
    }

    public function destroy(OverlayStyleModel $model): RedirectResponse
    {
        $model->delete();

        return back()->with('success', 'Modelo excluído.');
    }
}

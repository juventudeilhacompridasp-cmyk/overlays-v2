<?php

namespace App\Modules\Arts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Arts\Actions\StoreArtAsset;
use App\Modules\Arts\Models\ArtAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Imagens da organização para as artes (fundos, logotipos e selos). Enviar/excluir: `manage-competitions`; ler: qualquer membro. */
class AssetController extends Controller
{
    public function store(Request $request, StoreArtAsset $store): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'kind' => ['required', Rule::in(array_keys(ArtAsset::KINDS))],
            'name' => ['nullable', 'string', 'max:80'],
        ], [], ['file' => 'imagem', 'kind' => 'tipo', 'name' => 'nome']);

        $name = trim((string) ($data['name'] ?? '')) !== '' ? trim($data['name']) : pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);

        try {
            $store->handle($request->file('file'), $data['kind'], mb_substr($name, 0, 80), $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with('success', 'Imagem enviada.');
    }

    public function destroy(ArtAsset $asset, StoreArtAsset $store): RedirectResponse
    {
        $store->delete($asset);

        return back()->with('success', 'Imagem excluída.');
    }

    /** Bytes da imagem para o canvas do editor (mesma origem; só da organização atual). */
    public function file(ArtAsset $asset): Response
    {
        $contents = $asset->contents();

        abort_if($contents === null, 404);

        return response($contents, 200, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400']);
    }
}

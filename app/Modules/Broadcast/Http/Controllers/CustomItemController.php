<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\SaveOverlayCustomItem;
use App\Modules\Broadcast\Models\OverlayCustomItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Overlays livres (Builder): criar, editar, duplicar, importar e excluir é de quem gerencia competições. */
class CustomItemController extends Controller
{
    public function store(Request $request, SaveOverlayCustomItem $save): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $item = $save->handle(null, $data['name'], $data['width'] ?? null, $data['height'] ?? null, $data['elements'] ?? []);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Overlay criado.')->with('custom_saved', $item->getKey());
    }

    public function update(Request $request, OverlayCustomItem $item, SaveOverlayCustomItem $save): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $save->handle($item, $data['name'], $data['width'] ?? null, $data['height'] ?? null, $data['elements'] ?? []);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Overlay salvo.');
    }

    public function duplicate(OverlayCustomItem $item, SaveOverlayCustomItem $save): RedirectResponse
    {
        try {
            $save->duplicate($item);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Overlay duplicado.');
    }

    /** Importa o arquivo exportado (`juventude-overlay`, versão 1); o conteúdo sempre é saneado. */
    public function import(Request $request, SaveOverlayCustomItem $save): RedirectResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:juventude-overlay'],
            'version' => ['required', 'integer', 'in:1'],
            'overlay' => ['required', 'array'],
            'overlay.name' => ['required', 'string', 'max:60'],
            'overlay.width' => ['nullable', 'integer'],
            'overlay.height' => ['nullable', 'integer'],
            'overlay.elements' => ['nullable', 'array', 'max:60'],
        ], [], ['overlay' => 'overlay', 'overlay.name' => 'nome']);

        $overlay = $data['overlay'];
        $name = $overlay['name'];

        // Nome já usado: importa como cópia numerada em vez de sobrescrever o que existe.
        for ($n = 2; OverlayCustomItem::query()->where('name', $name)->exists(); $n++) {
            $name = mb_substr($overlay['name'], 0, 52).' ('.$n.')';
        }

        try {
            $save->handle(null, $name, $overlay['width'] ?? null, $overlay['height'] ?? null, $overlay['elements'] ?? []);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Overlay importado.');
    }

    public function destroy(OverlayCustomItem $item, SaveOverlayCustomItem $save): RedirectResponse
    {
        $save->delete($item);

        return back()->with('success', 'Overlay excluído.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'width' => ['nullable', 'integer', 'between:200,3840'],
            'height' => ['nullable', 'integer', 'between:200,3840'],
            'elements' => ['nullable', 'array', 'max:60'],
        ], [], ['name' => 'nome', 'elements' => 'elementos']);
    }
}

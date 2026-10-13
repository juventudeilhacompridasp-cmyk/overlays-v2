<?php

namespace App\Modules\Arts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Arts\Models\ArtTemplate;
use App\Modules\Arts\Support\ArtFormats;
use App\Modules\Arts\Support\ArtStyle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Modelos de arte da organização (`manage-competitions`, ADR 0009): o estilo sempre passa por `ArtStyle::sanitize`. */
class TemplateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $template = ArtTemplate::query()->create([...$this->payload($request), 'created_by' => $request->user()->getKey()]);

        return $this->back($request, $template)->with('success', 'Modelo salvo.');
    }

    public function update(Request $request, ArtTemplate $template): RedirectResponse
    {
        $template->update($this->payload($request));

        return $this->back($request, $template)->with('success', 'Modelo atualizado.');
    }

    public function destroy(Request $request, ArtTemplate $template): RedirectResponse
    {
        $template->delete();

        $game = $request->input('game');

        return ($game ? redirect()->route('panel.arts.editor', ['game' => $game]) : redirect()->route('panel.arts.index'))->with('success', 'Modelo excluído.');
    }

    /** @return array{name: string, format: string, width: int, height: int, style: array<string, array<string, mixed>>} */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'format' => ['required', 'string', 'max:20'],
            'width' => ['nullable', 'integer'],
            'height' => ['nullable', 'integer'],
            'style' => ['required', 'array'],
        ], [], ['name' => 'nome', 'format' => 'formato', 'style' => 'estilo']);

        $format = ArtFormats::resolve($data['format'], $data['width'] ?? null, $data['height'] ?? null);

        return [
            'name' => trim($data['name']),
            'format' => $format['key'],
            'width' => $format['width'],
            'height' => $format['height'],
            'style' => ArtStyle::sanitize($data['style']),
        ];
    }

    private function back(Request $request, ArtTemplate $template): RedirectResponse
    {
        $game = $request->input('game');

        return $game
            ? redirect()->route('panel.arts.editor', ['game' => $game, 'template' => $template->getKey()])
            : redirect()->route('panel.arts.index');
    }
}

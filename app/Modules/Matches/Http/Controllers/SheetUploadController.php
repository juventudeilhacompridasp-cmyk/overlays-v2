<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\StoreGameSheetUpload;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameSheetUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Súmulas enviadas depois do jogo (ADR 0004): enviar, listar, abrir por URL assinada e excluir. */
class SheetUploadController extends Controller
{
    public function index(Game $game): Response
    {
        $game->load(['homeEntry.team', 'awayEntry.team', 'division.season.competition']);

        return Inertia::render('Panel/Games/Uploads', [
            'game' => [
                'id' => $game->getKey(),
                'home' => $game->homeEntry?->team->name,
                'away' => $game->awayEntry?->team->name,
                'division' => $game->division->name,
                'season' => $game->division->season->name,
                'competition' => $game->division->season->competition->name,
                'scheduled_at' => $game->scheduled_at?->toIso8601String(),
            ],
            'max_mb' => intdiv(StoreGameSheetUpload::MAX_KB, 1024),
            'uploads' => GameSheetUpload::query()->where('game_id', $game->getKey())->with('uploader:id,name')->orderByDesc('created_at')->get()->map(fn (GameSheetUpload $upload) => [
                'id' => $upload->getKey(),
                'name' => $upload->original_name,
                'kind' => $upload->kind,
                'size' => $upload->size,
                'status' => $upload->status->value,
                'status_label' => $upload->status->label(),
                'error' => $upload->error,
                'uploaded_by' => $upload->uploader?->name,
                'created_at' => $upload->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request, Game $game, StoreGameSheetUpload $store): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.StoreGameSheetUpload::MAX_KB]], [], ['file' => 'arquivo']);

        try {
            $store->handle($game, $request->file('file'), $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Súmula enviada.');
    }

    /** Abre o arquivo por uma URL temporária assinada (dado pessoal: nunca público). */
    public function show(GameSheetUpload $upload, StoreGameSheetUpload $store): RedirectResponse
    {
        $url = $store->signedUrl($upload);
        abort_if($url === null, 404);

        return redirect()->away($url);
    }

    public function destroy(GameSheetUpload $upload, StoreGameSheetUpload $store): RedirectResponse
    {
        $store->remove($upload);

        return back()->with('success', 'Súmula excluída.');
    }
}

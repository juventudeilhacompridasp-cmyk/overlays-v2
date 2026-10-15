<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\RequestSheetRead;
use App\Modules\Matches\Actions\StoreGameSheetUpload;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Matches\Reading\SheetImportSettings;
use App\Modules\Matches\Reading\StructuredSheetReader;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
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
            'ai' => [
                'enabled' => SheetImportSettings::aiEnabled(),
                'available' => SheetImportSettings::aiAvailable(),
                'reads_left' => max(0, SheetImportSettings::maxReadsPerGame() - SheetImportSettings::usedForGame($game->getKey())),
                'max_reads_per_game' => SheetImportSettings::maxReadsPerGame(),
                'ceiling' => SheetImportSettings::ceiling(),
                'can_configure' => Gate::allows('manage-users'),
            ],
            'uploads' => GameSheetUpload::query()->where('game_id', $game->getKey())->with('uploader:id,name')->orderByDesc('created_at')->get()->map(fn (GameSheetUpload $upload) => [
                'id' => $upload->getKey(),
                'name' => $upload->original_name,
                'kind' => $upload->kind,
                'size' => $upload->size,
                'status' => $upload->status->value,
                'status_label' => $upload->status->label(),
                'error' => $upload->error,
                'summary' => $this->summary($upload),
                'uploaded_by' => $upload->uploader?->name,
                'created_at' => $upload->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Resumo do que foi lido (a revisão completa vem na parte 3 da ADR 0004).
     *
     * @return array<string, mixed>|null
     */
    private function summary(GameSheetUpload $upload): ?array
    {
        $data = $upload->extracted;

        if ($data === null) {
            return null;
        }

        return [
            'home_team' => $data['home_team'] ?? null,
            'away_team' => $data['away_team'] ?? null,
            'home_score' => $data['home_score'] ?? null,
            'away_score' => $data['away_score'] ?? null,
            'lineups' => count($data['lineups'] ?? []),
            'events' => count($data['events'] ?? []),
            'notes' => $data['notes'] ?? null,
            'confidence' => $data['confidence'] ?? null,
        ];
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

    /** Pede a leitura (estruturada na hora; por IA em fila, sujeita ao interruptor e aos limites). */
    public function read(Request $http, GameSheetUpload $upload, RequestSheetRead $request): RedirectResponse
    {
        try {
            $request->handle($upload, $http->user() !== null ? (int) $http->user()->getAuthIdentifier() : null);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Leitura pedida. Atualize a página em instantes para ver o resultado.');
    }

    /** Interruptor da leitura por IA e limite de leituras por jogo da organização (só administrador). */
    public function settings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
            'max_reads_per_game' => ['required', 'integer', 'between:1,'.SheetImportSettings::ceiling()],
        ], [], ['max_reads_per_game' => 'leituras por jogo']);

        $organization = CurrentOrganization::get();
        abort_if($organization === null, 404);

        $settings = $organization->settings ?? [];
        $settings['sheet_import'] = $validated;
        $organization->update(['settings' => $settings]);

        return back()->with('success', 'Configuração da leitura de súmulas salva.');
    }

    /** Modelo de planilha CSV para a súmula digital. */
    public function template(): HttpResponse
    {
        return response("\xEF\xBB\xBF".StructuredSheetReader::TEMPLATE."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo-sumula.csv"',
        ]);
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

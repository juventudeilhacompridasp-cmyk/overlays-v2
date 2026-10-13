<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Support\SeasonReports;
use App\Modules\Matches\Actions\ConfirmSheetReview;
use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Matches\Reading\BuildSheetFacts;
use App\Modules\Matches\Reading\ExtractedSheet;
use App\Modules\Matches\Reading\SheetComparison;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Revisão humana da leitura e relatório de conferência da súmula enviada (ADR 0004, parte 3).
 * Nada aqui altera o jogo: a conferência só compara a súmula revisada com o que o sistema já tem.
 */
class SheetReviewController extends Controller
{
    private const EVENT_LABELS = [
        'goal' => 'Gol',
        'own_goal' => 'Gol contra',
        'yellow_card' => 'Cartão amarelo',
        'red_card' => 'Cartão vermelho',
        'substitution' => 'Substituição',
        'foul' => 'Falta',
        'timeout' => 'Pedido de tempo',
        'point' => 'Ponto',
        'other' => 'Outro',
    ];

    private const STATUS_LABELS = [
        'match' => 'Confere',
        'differs' => 'Diverge',
        'only_sheet' => 'Só na súmula',
        'only_system' => 'Só no sistema',
        'unclear' => 'Não lido',
    ];

    public function review(GameSheetUpload $upload): Response|RedirectResponse
    {
        if (! in_array($upload->status, [SheetUploadStatus::Read, SheetUploadStatus::Reviewed], true) || $upload->extracted === null) {
            return redirect()->route('panel.games.uploads', $upload->game_id)->with('error', 'Esta súmula ainda não foi lida.');
        }

        return Inertia::render('Panel/Games/SheetReview', [
            'upload' => $this->meta($upload),
            'sheet' => $upload->reviewed ?? $upload->extracted,
            'event_types' => self::EVENT_LABELS,
            'reviewed' => $upload->status === SheetUploadStatus::Reviewed,
            'changes' => $upload->review_changes ?? [],
        ]);
    }

    public function confirm(Request $request, GameSheetUpload $upload, ConfirmSheetReview $confirm): RedirectResponse
    {
        $data = $request->validate([
            'home_team' => ['nullable', 'string', 'max:120'],
            'away_team' => ['nullable', 'string', 'max:120'],
            'home_score' => ['nullable', 'integer', 'between:0,999'],
            'away_score' => ['nullable', 'integer', 'between:0,999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lineups' => ['array', 'max:60'],
            'lineups.*.team' => ['required', 'in:home,away'],
            'lineups.*.name' => ['nullable', 'string', 'max:120'],
            'lineups.*.shirt' => ['nullable', 'integer', 'between:0,999'],
            'lineups.*.starter' => ['nullable', 'boolean'],
            'events' => ['array', 'max:250'],
            'events.*.type' => ['required', 'in:'.implode(',', ExtractedSheet::EVENT_TYPES)],
            'events.*.team' => ['required', 'in:home,away'],
            'events.*.name' => ['nullable', 'string', 'max:120'],
            'events.*.shirt' => ['nullable', 'integer', 'between:0,999'],
            'events.*.minute' => ['nullable', 'string', 'max:8'],
            'events.*.detail' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $confirm->handle($upload, $data, $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('panel.games.uploads.report', $upload)->with('success', 'Revisão confirmada. Confira o relatório.');
    }

    public function report(GameSheetUpload $upload, BuildSheetFacts $facts): Response|RedirectResponse
    {
        if ($upload->status !== SheetUploadStatus::Reviewed || $upload->reviewed === null) {
            return redirect()->route('panel.games.uploads', $upload->game_id)->with('error', 'Revise a leitura antes de ver o relatório.');
        }

        $items = (new SheetComparison)->compare($upload->reviewed, $facts->handle($upload->game));
        $sections = [];

        foreach ($items as $item) {
            $sections[$item['section']][] = $item;
        }

        return Inertia::render('Panel/Games/SheetReport', [
            'upload' => $this->meta($upload),
            'summary' => SheetComparison::summary($items),
            'sections' => collect($sections)->map(fn (array $list, string $section) => ['title' => $section, 'items' => $list])->values(),
            'status_labels' => self::STATUS_LABELS,
            'changes' => $upload->review_changes ?? [],
            'reviewed_by' => $upload->reviewer?->name,
            'reviewed_at' => $upload->reviewed_at?->toIso8601String(),
            'notes' => $upload->reviewed['notes'] ?? null,
        ]);
    }

    public function csv(GameSheetUpload $upload, BuildSheetFacts $facts): StreamedResponse
    {
        abort_unless($upload->status === SheetUploadStatus::Reviewed && $upload->reviewed !== null, 404);

        $items = (new SheetComparison)->compare($upload->reviewed, $facts->handle($upload->game));

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Seção', 'Item', 'Situação', 'Súmula enviada', 'Sistema', 'Observação'], ';', '"', '');

            foreach ($items as $item) {
                fputcsv($out, array_map(fn (?string $value) => SeasonReports::safeCell((string) $value), [
                    $item['section'], $item['label'], self::STATUS_LABELS[$item['status']], $item['sheet'], $item['system'], $item['note'],
                ]), ';', '"', '');
            }

            fclose($out);
        }, 'conferencia-sumula-'.$upload->game_id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function discard(GameSheetUpload $upload, ConfirmSheetReview $confirm): RedirectResponse
    {
        try {
            $confirm->discard($upload);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('panel.games.uploads', $upload->game_id)->with('success', 'Súmula descartada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(GameSheetUpload $upload): array
    {
        $upload->loadMissing(['game.homeEntry.team', 'game.awayEntry.team', 'game.division.season']);
        $game = $upload->game;

        return [
            'id' => $upload->getKey(),
            'name' => $upload->original_name,
            'game_id' => $game->getKey(),
            'home' => $game->homeEntry?->team->name,
            'away' => $game->awayEntry?->team->name,
            'division' => $game->division->name,
            'season' => $game->division->season->name,
        ];
    }
}

<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Division;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Reports\Enums\ReportKind;
use App\Modules\Reports\Pdf\ReportImages;
use App\Modules\Reports\Pdf\ReportPdf;
use App\Modules\Reports\Support\ChampionshipReport;
use App\Modules\Reports\Support\GameReport;
use App\Modules\Reports\Support\ReportSections;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Painel > Relatórios ilustrados: escolher a partida ou a divisão, as seções, ver a prévia e baixar o PDF. O documento
 * da prévia é o mesmo que vira PDF (interno: nomes completos e cartões) e, em versão pública, o overlay da transmissão.
 */
final class VisualReportController extends Controller
{
    private const GAME_LIMIT = 200;

    private const DIVISION_LIMIT = 120;

    public function index(Request $request): Response
    {
        $kind = ReportKind::tryFrom((string) $request->query('tipo')) ?? ReportKind::Game;
        $gameId = is_string($request->query('jogo')) ? $request->query('jogo') : null;
        $divisionId = is_string($request->query('divisao')) ? $request->query('divisao') : null;
        $sections = ReportSections::pick($kind, $this->sections($request));

        return Inertia::render('Panel/VisualReports/Index', [
            'kind' => $kind->value,
            'selected' => ['game' => $gameId, 'division' => $divisionId, 'sections' => $sections],
            'sections' => ['game' => ReportSections::options(ReportKind::Game), 'championship' => ReportSections::options(ReportKind::Championship)],
            'games' => fn () => $this->games(),
            'divisions' => fn () => $this->divisions(),
            'document' => function () use ($kind, $gameId, $divisionId, $sections) {
                if ($kind === ReportKind::Game && $gameId !== null) {
                    $game = Game::query()->find($gameId);

                    return $game === null ? null : GameReport::build($game, $sections);
                }

                if ($kind === ReportKind::Championship && $divisionId !== null) {
                    $division = Division::query()->find($divisionId);

                    return $division === null ? null : ChampionshipReport::build($division, $sections);
                }

                return null;
            },
        ]);
    }

    public function game(Request $request, Game $game): HttpResponse
    {
        $document = GameReport::build($game, $this->sections($request));
        $name = 'relatorio-partida-'.Str::slug($document['subtitle']);

        return $this->download(ReportPdf::render($document, ReportImages::forGame($game)), $name);
    }

    public function championship(Request $request, Division $division): HttpResponse
    {
        $document = ChampionshipReport::build($division, $this->sections($request));
        $name = 'relatorio-campeonato-'.Str::slug($document['subtitle'].' '.$division->name);

        return $this->download(ReportPdf::render($document), $name);
    }

    /** @return list<string>|null `?secoes=a,b` ou `?secoes[]=a` */
    private function sections(Request $request): ?array
    {
        $raw = $request->query('secoes');

        return match (true) {
            is_array($raw) => array_values(array_map('strval', array_filter($raw, 'is_scalar'))),
            is_string($raw) && $raw !== '' => explode(',', $raw),
            default => null,
        };
    }

    private function download(string $pdf, string $name): HttpResponse
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::limit($name, 120, '').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return list<array<string, mixed>> jogos ao vivo, encerrados recentes e próximos (os mais relevantes primeiro) */
    private function games(): array
    {
        $order = [GameStatus::InProgress->value => 0, GameStatus::Finished->value => 1, GameStatus::Walkover->value => 1, GameStatus::Scheduled->value => 2];

        return Game::query()
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->whereNot('status', GameStatus::Cancelled)
            ->with(['homeEntry.team', 'awayEntry.team', 'division.season.competition'])
            ->orderByDesc('scheduled_at')->orderByDesc('id')
            ->limit(self::GAME_LIMIT)->get()
            ->map(fn (Game $game) => [
                'id' => $game->getKey(),
                'label' => $game->homeEntry->team->name.' x '.$game->awayEntry->team->name,
                'score' => in_array($game->status, [GameStatus::Scheduled, GameStatus::Postponed], true) ? null : $game->home_score.' x '.$game->away_score,
                'competition' => $game->division->season->competition->name.' · '.$game->division->name,
                'date' => $game->scheduled_at?->format('d/m/Y H:i'),
                'sort' => $game->scheduled_at?->getTimestamp() ?? 0,
                'status' => $game->status->label(),
                'rank' => $order[$game->status->value] ?? 3,
            ])
            ->sortBy(fn (array $game) => [$game['rank'], -$game['sort']])
            ->values()->all();
    }

    /** @return list<array{id: string, label: string}> */
    private function divisions(): array
    {
        return Division::query()
            ->with('season.competition')
            ->join('seasons', 'seasons.id', '=', 'divisions.season_id')
            ->orderByDesc('seasons.year')->orderByDesc('seasons.created_at')->orderBy('divisions.name')
            ->limit(self::DIVISION_LIMIT)
            ->get(['divisions.*'])
            ->map(fn (Division $division) => [
                'id' => $division->getKey(),
                'label' => $division->season->competition->name.' · '.$division->season->name.' · '.$division->name,
            ])->values()->all();
    }
}

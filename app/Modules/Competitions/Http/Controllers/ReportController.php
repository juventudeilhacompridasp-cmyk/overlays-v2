<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Support\SeasonReports;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Painel: relatórios por edição (pendências de cadastro e elencos aprovados), na tela e em CSV. */
class ReportController extends Controller
{
    private const LIMIT = 300;

    public function index(Request $request): Response
    {
        $seasons = Season::query()->with('competition')->orderByDesc('year')->orderBy('name')->get();
        $selected = $seasons->firstWhere('id', $request->query('season')) ?? $seasons->first();

        $pending = $selected ? SeasonReports::pending($selected) : [];
        $rosters = $selected ? SeasonReports::rosters($selected) : [];

        return Inertia::render('Panel/Reports/Index', [
            'seasons' => $seasons->map(fn (Season $season) => ['id' => $season->getKey(), 'name' => "{$season->competition->name} · {$season->name}"])->values(),
            'season_id' => $selected?->getKey(),
            'limit' => self::LIMIT,
            'pending' => ['total' => count($pending), 'rows' => array_slice($pending, 0, self::LIMIT)],
            'rosters' => ['total' => count($rosters), 'rows' => array_slice($rosters, 0, self::LIMIT)],
        ]);
    }

    public function csv(Season $season, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['pendencias', 'elencos'], true), 404);

        $rows = $type === 'pendencias' ? SeasonReports::pending($season) : SeasonReports::rosters($season);
        $headers = $type === 'pendencias'
            ? ['Divisão', 'Equipe', 'Camisa', 'Nome', 'Função', 'Pendências']
            : ['Divisão', 'Equipe', 'Camisa', 'Nome', 'Apelido', 'Função', 'Nascimento', 'CPF informado', 'Foto'];

        $filename = "{$type}-{$season->year}-".str($season->name)->slug().'.csv';

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos corretamente
            fputcsv($out, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (string $value) => SeasonReports::safeCell($value), array_values($row)), ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

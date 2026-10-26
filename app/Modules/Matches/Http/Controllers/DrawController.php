<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\DrawStage;
use App\Modules\Matches\Http\Requests\DrawRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Sorteio e chaves de uma fase: a tela deixa arrastar as equipes (ou sortear) e este controlador só grava o resultado
 * já definido. Serve também para sorteios feitos fora da plataforma.
 */
class DrawController extends Controller
{
    public function show(Stage $stage): Response
    {
        $stage->load('division.season.competition');

        $entries = Entry::query()
            ->where('division_id', $stage->division_id)
            ->where('status', EntryStatus::Confirmed)
            ->with('team')
            ->inDrawOrder()
            ->get()
            ->map(fn (Entry $entry) => [
                'id' => $entry->getKey(),
                'name' => $entry->team->name,
                'short_name' => $entry->team->short_name,
                'logo_url' => $entry->team->logoUrl(128),
                'color' => $entry->team->primary_color,
                'city' => $entry->team->city,
                'club_key' => $entry->club_key,
                'sponsor_key' => $entry->sponsor_key,
                'seed' => $entry->seed,
            ])->values();

        return Inertia::render('Panel/Stages/Draw', [
            'competition' => $stage->division->season->competition->only(['id', 'name']),
            'season' => $stage->division->season->only(['id', 'name']),
            'division' => $stage->division->only(['id', 'name']),
            'stage' => ['id' => $stage->getKey(), 'name' => $stage->name, 'type' => $stage->type->value, 'type_label' => $stage->type->label()],
            'entries' => $entries,
            'blocked' => $stage->games()->exists() ? 'Esta fase já tem jogos. Limpe a tabela da fase para sortear de novo.' : null,
            'supported' => true,
        ]);
    }

    public function store(DrawRequest $request, Stage $stage, DrawStage $draw): RedirectResponse
    {
        try {
            $created = match ($stage->type) {
                StageType::Knockout => $draw->knockout($stage, $request->pairs(), $request->integer('legs', 1), $request->boolean('partial')),
                StageType::League => $draw->league($stage, $request->pairs(), $request->boolean('double_round')),
                StageType::Groups => $draw->groups($stage, $request->groupList(), $request->boolean('double_round')),
            };
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('panel.stages.show', $stage)->with('success', "Sorteio salvo: {$created} jogos criados.");
    }
}

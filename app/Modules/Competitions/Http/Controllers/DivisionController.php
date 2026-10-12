<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Http\Requests\DivisionRequest;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    public function store(DivisionRequest $request, Season $season): RedirectResponse
    {
        $division = $season->divisions()->create($request->validated());

        return to_route('panel.divisions.show', $division)->with('success', 'Divisão criada. Cadastre as fases e inscreva as equipes.');
    }

    public function show(Division $division): Response
    {
        $division->load('season.competition');

        $stages = $division->stages()->orderBy('sequence')->get()->map(fn ($stage) => [
            'id' => $stage->id,
            'name' => $stage->name,
            'sequence' => $stage->sequence,
            'type' => $stage->type->value,
            'type_label' => $stage->type->label(),
        ]);

        $entries = $division->entries()->with('team')->get()
            ->sortBy(fn ($entry) => [$entry->seed ?? PHP_INT_MAX, $entry->team->name])
            ->values()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'seed' => $entry->seed,
                'status' => $entry->status->value,
                'status_label' => $entry->status->label(),
                'team' => ['id' => $entry->team->id, 'name' => $entry->team->name, 'city' => $entry->team->city],
            ]);

        $availableTeams = Team::query()
            ->whereNotIn('id', $division->entries()->select('team_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Panel/Divisions/Show', [
            'competition' => $division->season->competition->only(['id', 'name']),
            'season' => $division->season->only(['id', 'name']),
            'division' => [
                'id' => $division->id,
                'name' => $division->name,
                'category' => $division->category,
                'gender' => $division->gender->value,
                'gender_label' => $division->gender->label(),
            ],
            'stages' => $stages,
            'entries' => $entries,
            'available_teams' => $availableTeams,
            'options' => [
                'stage_types' => array_map(fn (StageType $t) => ['value' => $t->value, 'label' => $t->label()], StageType::cases()),
                'entry_statuses' => array_map(fn (EntryStatus $s) => ['value' => $s->value, 'label' => $s->label()], EntryStatus::cases()),
                'next_sequence' => ((int) $division->stages()->max('sequence')) + 1,
            ],
        ]);
    }

    public function update(DivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->validated());

        return back()->with('success', 'Divisão atualizada.');
    }

    public function destroy(Division $division): RedirectResponse
    {
        $season = $division->season;
        $division->delete();

        return to_route('panel.seasons.show', $season)->with('success', 'Divisão excluída, com suas fases e jogos.');
    }
}

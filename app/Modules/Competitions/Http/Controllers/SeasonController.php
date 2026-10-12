<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\Gender;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Http\Requests\SeasonRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Tiebreaker;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    public function create(Competition $competition): Response
    {
        return Inertia::render('Panel/Seasons/Form', [
            'competition' => $competition->only(['id', 'name']),
            'season' => null,
            'rules' => RuleSet::defaults()->toArray(),
            'options' => $this->options(),
            'defaults' => ['year' => (int) now()->format('Y')],
        ]);
    }

    public function store(SeasonRequest $request, Competition $competition): RedirectResponse
    {
        $season = $competition->seasons()->create($request->seasonData());

        return to_route('panel.seasons.show', $season)->with('success', 'Edição criada. Agora cadastre as divisões (categorias).');
    }

    public function show(Season $season): Response
    {
        $season->load('competition');

        $divisions = $season->divisions()
            ->withCount(['entries', 'stages'])
            ->orderBy('name')
            ->get()
            ->map(fn ($division) => [
                'id' => $division->id,
                'name' => $division->name,
                'category' => $division->category,
                'gender' => $division->gender->value,
                'gender_label' => $division->gender->label(),
                'entries_count' => $division->entries_count,
                'stages_count' => $division->stages_count,
            ]);

        return Inertia::render('Panel/Seasons/Show', [
            'competition' => $season->competition->only(['id', 'name']),
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'year' => $season->year,
                'starts_on' => $season->starts_on?->format('Y-m-d'),
                'ends_on' => $season->ends_on?->format('Y-m-d'),
                'status' => $season->status->value,
                'status_label' => $season->status->label(),
            ],
            'rules' => $season->ruleSet()->toArray(),
            'tiebreaker_labels' => $this->tiebreakerLabels(),
            'divisions' => $divisions,
            'options' => $this->options(),
        ]);
    }

    public function edit(Season $season): Response
    {
        $season->load('competition');

        return Inertia::render('Panel/Seasons/Form', [
            'competition' => $season->competition->only(['id', 'name']),
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'year' => $season->year,
                'starts_on' => $season->starts_on?->format('Y-m-d'),
                'ends_on' => $season->ends_on?->format('Y-m-d'),
                'status' => $season->status->value,
            ],
            'rules' => $season->ruleSet()->toArray(),
            'options' => $this->options(),
            'defaults' => ['year' => $season->year],
        ]);
    }

    public function update(SeasonRequest $request, Season $season): RedirectResponse
    {
        $season->update($request->seasonData());

        return to_route('panel.seasons.show', $season)->with('success', 'Edição atualizada.');
    }

    public function destroy(Season $season): RedirectResponse
    {
        $competition = $season->competition;
        $season->delete();

        return to_route('panel.competitions.show', $competition)->with('success', 'Edição excluída, com suas divisões, fases e jogos.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'statuses' => array_map(fn (SeasonStatus $s) => ['value' => $s->value, 'label' => $s->label()], SeasonStatus::cases()),
            'genders' => array_map(fn (Gender $g) => ['value' => $g->value, 'label' => $g->label()], Gender::cases()),
            'entry_statuses' => array_map(fn (EntryStatus $s) => ['value' => $s->value, 'label' => $s->label()], EntryStatus::cases()),
            'tiebreakers' => array_map(fn (Tiebreaker $t) => ['value' => $t->value, 'label' => $t->label()], Tiebreaker::cases()),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function tiebreakerLabels(): array
    {
        $labels = [];

        foreach (Tiebreaker::cases() as $tiebreaker) {
            $labels[$tiebreaker->value] = $tiebreaker->label();
        }

        return $labels;
    }
}

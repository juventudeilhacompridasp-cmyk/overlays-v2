<?php

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Tiebreaker;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Season|null $season */
        $season = $this->route('season');
        /** @var Competition|null $competition */
        $competition = $this->route('competition') ?? $season?->competition;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('seasons', 'name')->where('competition_id', $competition?->getKey())->ignore($season?->getKey()),
            ],
            'year' => ['required', 'integer', 'between:1950,2100'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(SeasonStatus::class)],

            'rules' => ['required', 'array'],
            'rules.points.win' => ['required', 'integer', 'between:0,10'],
            'rules.points.draw' => ['required', 'integer', 'between:0,10'],
            'rules.points.loss' => ['required', 'integer', 'between:0,10'],
            'rules.tiebreakers' => ['required', 'array', 'min:1'],
            'rules.tiebreakers.*' => ['distinct', Rule::enum(Tiebreaker::class)],
            'rules.cards.yellow_for_suspension' => ['required', 'integer', 'between:1,10'],
            'rules.cards.yellow_suspension_games' => ['required', 'integer', 'between:0,10'],
            'rules.cards.red_suspension_games' => ['required', 'integer', 'between:0,10'],
            'rules.cards.reset_yellows_between_stages' => ['required', 'boolean'],
            'rules.cards.second_yellow_counts_for_series' => ['required', 'boolean'],
            'rules.walkover.winner_goals' => ['required', 'integer', 'between:0,20'],
            'rules.walkover.loser_goals' => ['required', 'integer', 'between:0,20'],
            'rules.substitutions.max' => ['required', 'integer', 'between:0,99'],
            'rules.substitutions.max_windows' => ['required', 'integer', 'between:0,99'],
            'rules.substitutions.allow_reentry' => ['sometimes', 'boolean'],
            'rules.lineup.manager_deadline_minutes' => ['sometimes', 'integer', 'between:0,1440'],
            'rules.fouls.max_player' => ['sometimes', 'nullable', 'integer', 'between:1,20'],
            'rules.fouls.max_disqualifying' => ['sometimes', 'integer', 'between:1,5'],
            'rules.points.walkover_loss' => ['sometimes', 'nullable', 'integer', 'between:0,10'],
            'rules.scoring.sets_to_win' => ['sometimes', 'integer', 'between:1,5'],
            'rules.scoring.set_points' => ['sometimes', 'integer', 'between:1,99'],
            'rules.scoring.deciding_set_points' => ['sometimes', 'integer', 'between:1,99'],
            'rules.scoring.min_lead' => ['sometimes', 'integer', 'between:1,10'],
            'rules.scoring.points_narrow_win' => ['sometimes', 'nullable', 'integer', 'between:0,10'],
            'rules.scoring.points_narrow_loss' => ['sometimes', 'nullable', 'integer', 'between:0,10'],
            'rules.results.quick_allowed' => ['sometimes', 'boolean'],
            'rules.periods.minutes' => ['sometimes', 'nullable', 'array'],
            'rules.periods.minutes.*' => ['integer', 'between:1,120'],
            'rules.fouls.team_limit' => ['sometimes', 'nullable', 'integer', 'between:1,20'],
            'rules.timeouts.limits' => ['sometimes', 'nullable', 'array'],
            'rules.timeouts.limits.*' => ['integer', 'between:0,10'],
        ];
    }

    /**
     * Lista de durações precisa ter um valor por período do esporte (a edição não troca o formato do jogo).
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $competition = $this->route('competition') ?? $this->route('season')?->competition;
            $minutes = $this->input('rules.periods.minutes');

            if ($competition instanceof Competition && is_array($minutes) && count($minutes) !== count($competition->sport->profile()->periodMinutes)) {
                $validator->errors()->add('rules.periods.minutes', 'Informe a duração de cada período do esporte.');
            }
        }];
    }

    /**
     * Dados prontos para gravar: as regras passam pelo RuleSet (valida e normaliza).
     *
     * @return array<string, mixed>
     */
    public function seasonData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        /** @var array<string, mixed> $rules */
        $rules = $validated['rules'];
        $competition = $this->route('competition') ?? $this->route('season')?->competition;
        $sportDefaults = $competition instanceof Competition ? $competition->sport->profile()->ruleDefaults : [];
        $validated['rules'] = RuleSet::fromArray($rules, $sportDefaults)->toArray();

        return $validated;
    }
}

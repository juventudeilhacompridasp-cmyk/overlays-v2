<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;

/**
 * Extras da página pública da edição, parametrizáveis por organização (`PortalSettings`): chaveamento visual do
 * mata-mata e o regulamento da tabela explicado (pontos e critérios de desempate). Só texto do regulamento e
 * placares; nada de cartões individuais, atletas ou dados pessoais.
 */
final class SeasonExtras
{
    /**
     * @return array{brackets: array<string, mixed>|null, rules: array<string, mixed>|null}
     */
    public function for(Season $season): array
    {
        $season->loadMissing('divisions.stages');

        $brackets = null;
        $rules = null;

        if (PortalSettings::enabled('season_bracket')) {
            $brackets = [];

            foreach ($season->divisions as $division) {
                foreach ($division->stages as $stage) {
                    if ($stage->type === StageType::Knockout && ($bracket = BracketView::for($stage)) !== null) {
                        $brackets[$stage->getKey()] = $bracket;
                    }
                }
            }
        }

        if (PortalSettings::enabled('season_rules')) {
            $rules = $season->divisions->mapWithKeys(fn (Division $division) => [$division->getKey() => $this->rules($division)])->all();
        }

        return ['brackets' => $brackets, 'rules' => $rules];
    }

    /**
     * @return array{points: list<array{label: string, value: int}>, tiebreakers: list<string>}
     */
    private function rules(Division $division): array
    {
        $rules = $division->ruleSet();

        $points = [['label' => 'Vitória', 'value' => $rules->pointsWin]];

        if ($rules->pointsNarrowWin !== null) {
            $points[] = ['label' => 'Vitória apertada (ex.: 3 x 2 em sets)', 'value' => $rules->pointsNarrowWin];
        }

        // Só futebol e futsal têm empate; vôlei e basquete não.
        if ($rules->scoreModel === 'goals') {
            $points[] = ['label' => 'Empate', 'value' => $rules->pointsDraw];
        }

        if ($rules->pointsNarrowLoss !== null) {
            $points[] = ['label' => 'Derrota apertada (ex.: 2 x 3 em sets)', 'value' => $rules->pointsNarrowLoss];
        }

        $points[] = ['label' => 'Derrota', 'value' => $rules->pointsLoss];

        if ($rules->pointsWalkoverLoss !== null) {
            $points[] = ['label' => 'Derrota por W.O.', 'value' => $rules->pointsWalkoverLoss];
        }

        return ['points' => $points, 'tiebreakers' => array_map(fn ($tiebreaker) => $tiebreaker->label(), $rules->tiebreakers)];
    }
}

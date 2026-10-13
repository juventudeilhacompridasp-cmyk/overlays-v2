<?php

namespace App\Modules\Rules\Sports;

use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Sheet\GameClock;

/**
 * Particularidades de um esporte (código puro): duração dos períodos, padrões do regulamento
 * e se há contagem de faltas acumuladas por equipe. O regulamento continua sendo parâmetro
 * ({@see RuleSet}); o perfil só define os valores iniciais de cada esporte.
 */
final readonly class SportProfile
{
    /**
     * @param  list<int>  $periodMinutes  duração nominal de cada período (tempos + prorrogações), em minutos
     * @param  array<string, mixed>  $ruleDefaults  sobrescritas dos padrões do {@see RuleSet::toArray()}
     * @param  int|null  $teamFoulLimit  faltas acumuladas que dão tiro livre direto (null = o esporte não conta faltas)
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $periodMinutes,
        public array $ruleDefaults = [],
        public ?int $teamFoulLimit = null,
        /** goals | sets | points (ver ADR 0002). */
        public string $scoreModel = 'goals',
        /** up = relógio corrido (futebol); down = tempo restante do período (basquete). */
        public string $clockDirection = 'up',
        /** O relógio para e volta dentro do período (basquete). */
        public bool $pausableClock = false,
        /** Grupo de faltas de cada período, na ordem; períodos além da lista ficam no último grupo. */
        public array $foulGroups = [1, 2],
    ) {}

    public function clock(): GameClock
    {
        return new GameClock($this->periodMinutes);
    }

    /** O jogo tem cronômetro? (vôlei não tem: o período é o set). */
    public function hasClock(): bool
    {
        return $this->periodMinutes !== [];
    }

    public function tracksTeamFouls(): bool
    {
        return $this->teamFoulLimit !== null;
    }

    /** Regulamento inicial do esporte (padrões gerais com as sobrescritas do perfil). */
    public function defaultRules(): RuleSet
    {
        return RuleSet::fromArray([], $this->ruleDefaults);
    }

    /**
     * Grupo de contagem de faltas de um período: as faltas zeram a cada tempo regulamentar,
     * e as prorrogações seguem acumulando com as do 2º tempo.
     */
    public function foulGroup(int $period): int
    {
        $groups = $this->foulGroups === [] ? [1] : $this->foulGroups;

        return $groups[min(max($period, 1), count($groups)) - 1];
    }
}

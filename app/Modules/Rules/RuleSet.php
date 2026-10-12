<?php

namespace App\Modules\Rules;

use InvalidArgumentException;
use ValueError;

/**
 * Regulamento de futebol como parâmetros. Os valores padrão seguem o regulamento técnico de referência
 * (3/1/0 pontos; 3 amarelos = 1 jogo; vermelho = 1 jogo; até 8 substituições em 3 atos).
 */
final readonly class RuleSet
{
    /**
     * @param  list<Tiebreaker>  $tiebreakers
     */
    public function __construct(
        public int $pointsWin = 3,
        public int $pointsDraw = 1,
        public int $pointsLoss = 0,
        public array $tiebreakers = self::DEFAULT_TIEBREAKERS,
        public int $yellowCardsForSuspension = 3,
        public int $yellowSuspensionGames = 1,
        public int $redSuspensionGames = 1,
        public bool $resetYellowsBetweenStages = true,
        public bool $secondYellowCountsForSeries = false,
        public int $walkoverWinnerGoals = 3,
        public int $walkoverLoserGoals = 0,
        public int $maxSubstitutions = 8,
        public int $maxSubstitutionWindows = 3,
        public int $maxStarters = 11,
        public int $maxBench = 11,
        public int $maxStaff = 3,
    ) {
        if ($yellowCardsForSuspension < 1) {
            throw new InvalidArgumentException('cards.yellow_for_suspension deve ser pelo menos 1.');
        }

        foreach (['pointsWin' => $pointsWin, 'pointsDraw' => $pointsDraw, 'pointsLoss' => $pointsLoss,
            'yellowSuspensionGames' => $yellowSuspensionGames, 'redSuspensionGames' => $redSuspensionGames,
            'walkoverWinnerGoals' => $walkoverWinnerGoals, 'walkoverLoserGoals' => $walkoverLoserGoals,
            'maxSubstitutions' => $maxSubstitutions, 'maxSubstitutionWindows' => $maxSubstitutionWindows,
            'maxStarters' => $maxStarters, 'maxBench' => $maxBench, 'maxStaff' => $maxStaff] as $name => $value) {
            if ($value < 0) {
                throw new InvalidArgumentException("O valor de {$name} não pode ser negativo.");
            }
        }
    }

    /**
     * Ordem do regulamento de referência: vitórias, saldo, gols pró, confronto direto,
     * menos vermelhos, menos amarelos e, por fim, sorteio.
     */
    public const DEFAULT_TIEBREAKERS = [
        Tiebreaker::Wins,
        Tiebreaker::GoalDifference,
        Tiebreaker::GoalsFor,
        Tiebreaker::HeadToHead,
        Tiebreaker::FewestRedCards,
        Tiebreaker::FewestYellowCards,
        Tiebreaker::Draw,
    ];

    public static function defaults(): self
    {
        return new self;
    }

    /**
     * Monta a partir do JSON salvo na edição/divisão. Chaves ausentes usam o padrão.
     *
     * @param  array<string, mixed>  $rules
     */
    public static function fromArray(array $rules): self
    {
        $defaults = self::defaults()->toArray();
        $merged = array_replace_recursive($defaults, $rules);

        if (array_key_exists('tiebreakers', $rules)) {
            $merged['tiebreakers'] = $rules['tiebreakers'];
        }

        $tiebreakers = [];
        foreach ((array) $merged['tiebreakers'] as $value) {
            try {
                $tiebreakers[] = Tiebreaker::from((string) $value);
            } catch (ValueError) {
                throw new InvalidArgumentException("Critério de desempate inválido: {$value}.");
            }
        }

        return new self(
            pointsWin: (int) $merged['points']['win'],
            pointsDraw: (int) $merged['points']['draw'],
            pointsLoss: (int) $merged['points']['loss'],
            tiebreakers: $tiebreakers,
            yellowCardsForSuspension: (int) $merged['cards']['yellow_for_suspension'],
            yellowSuspensionGames: (int) $merged['cards']['yellow_suspension_games'],
            redSuspensionGames: (int) $merged['cards']['red_suspension_games'],
            resetYellowsBetweenStages: (bool) $merged['cards']['reset_yellows_between_stages'],
            secondYellowCountsForSeries: (bool) $merged['cards']['second_yellow_counts_for_series'],
            walkoverWinnerGoals: (int) $merged['walkover']['winner_goals'],
            walkoverLoserGoals: (int) $merged['walkover']['loser_goals'],
            maxSubstitutions: (int) $merged['substitutions']['max'],
            maxSubstitutionWindows: (int) $merged['substitutions']['max_windows'],
            maxStarters: (int) $merged['lineup']['max_starters'],
            maxBench: (int) $merged['lineup']['max_bench'],
            maxStaff: (int) $merged['lineup']['max_staff'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'points' => ['win' => $this->pointsWin, 'draw' => $this->pointsDraw, 'loss' => $this->pointsLoss],
            'tiebreakers' => array_map(fn (Tiebreaker $t) => $t->value, $this->tiebreakers),
            'cards' => [
                'yellow_for_suspension' => $this->yellowCardsForSuspension,
                'yellow_suspension_games' => $this->yellowSuspensionGames,
                'red_suspension_games' => $this->redSuspensionGames,
                'reset_yellows_between_stages' => $this->resetYellowsBetweenStages,
                'second_yellow_counts_for_series' => $this->secondYellowCountsForSeries,
            ],
            'walkover' => ['winner_goals' => $this->walkoverWinnerGoals, 'loser_goals' => $this->walkoverLoserGoals],
            'substitutions' => ['max' => $this->maxSubstitutions, 'max_windows' => $this->maxSubstitutionWindows],
            'lineup' => ['max_starters' => $this->maxStarters, 'max_bench' => $this->maxBench, 'max_staff' => $this->maxStaff],
        ];
    }
}

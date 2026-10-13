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
        public bool $allowSubstitutionReentry = false,
        /** Modelo de placar do esporte (vem do perfil do esporte, não é editável): goals | sets | points. */
        public string $scoreModel = 'goals',
        public int $setsToWin = 3,
        public int $setPoints = 25,
        public int $decidingSetPoints = 15,
        public int $setMinLead = 2,
        /** Pontos na tabela para vitória/derrota por placar apertado de sets (ex.: 3-2 no vôlei). Nulo = igual a vitória/derrota. */
        public ?int $pointsNarrowWin = null,
        public ?int $pointsNarrowLoss = null,
        /** Pontos na tabela da equipe que perde por W.O. (nulo = igual à derrota normal). Basquete: 0. */
        public ?int $pointsWalkoverLoss = null,
        /** Faltas pessoais que eliminam o atleta (nulo = esporte sem esse controle). Basquete: 5. */
        public ?int $maxPlayerFouls = null,
        /** Minutos antes do horário do jogo em que a escalação do gestor da equipe trava (0 = no horário do jogo). */
        public int $managerLineupDeadlineMinutes = 30,
    ) {
        foreach (['setsToWin' => $setsToWin, 'setPoints' => $setPoints, 'decidingSetPoints' => $decidingSetPoints, 'setMinLead' => $setMinLead] as $name => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException("O valor de {$name} deve ser pelo menos 1.");
            }
        }

        if ($yellowCardsForSuspension < 1) {
            throw new InvalidArgumentException('cards.yellow_for_suspension deve ser pelo menos 1.');
        }

        foreach (['pointsWin' => $pointsWin, 'pointsDraw' => $pointsDraw, 'pointsLoss' => $pointsLoss,
            'yellowSuspensionGames' => $yellowSuspensionGames, 'redSuspensionGames' => $redSuspensionGames,
            'walkoverWinnerGoals' => $walkoverWinnerGoals, 'walkoverLoserGoals' => $walkoverLoserGoals,
            'maxSubstitutions' => $maxSubstitutions, 'maxSubstitutionWindows' => $maxSubstitutionWindows,
            'maxStarters' => $maxStarters, 'maxBench' => $maxBench, 'maxStaff' => $maxStaff,
            'pointsNarrowWin' => $pointsNarrowWin ?? 0, 'pointsNarrowLoss' => $pointsNarrowLoss ?? 0,
            'pointsWalkoverLoss' => $pointsWalkoverLoss ?? 0, 'maxPlayerFouls' => $maxPlayerFouls ?? 0,
            'managerLineupDeadlineMinutes' => $managerLineupDeadlineMinutes] as $name => $value) {
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
     * @param  array<string, mixed>  $sportDefaults  sobrescritas dos padrões feitas pelo esporte (ver SportProfile)
     */
    public static function fromArray(array $rules, array $sportDefaults = []): self
    {
        $defaults = array_replace_recursive(self::defaults()->toArray(), $sportDefaults);

        // Lista de desempates do esporte substitui a padrão por inteiro (não se mescla por posição).
        if (isset($sportDefaults['tiebreakers'])) {
            $defaults['tiebreakers'] = $sportDefaults['tiebreakers'];
        }

        $merged = array_replace_recursive($defaults, $rules);
        // O modelo de placar é do esporte, nunca do JSON salvo.
        $merged['scoring']['model'] = $sportDefaults['scoring']['model'] ?? 'goals';

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
            allowSubstitutionReentry: (bool) $merged['substitutions']['allow_reentry'],
            scoreModel: (string) $merged['scoring']['model'],
            setsToWin: (int) $merged['scoring']['sets_to_win'],
            setPoints: (int) $merged['scoring']['set_points'],
            decidingSetPoints: (int) $merged['scoring']['deciding_set_points'],
            setMinLead: (int) $merged['scoring']['min_lead'],
            pointsNarrowWin: isset($merged['scoring']['points_narrow_win']) ? (int) $merged['scoring']['points_narrow_win'] : null,
            pointsNarrowLoss: isset($merged['scoring']['points_narrow_loss']) ? (int) $merged['scoring']['points_narrow_loss'] : null,
            pointsWalkoverLoss: isset($merged['points']['walkover_loss']) ? (int) $merged['points']['walkover_loss'] : null,
            maxPlayerFouls: isset($merged['fouls']['max_player']) ? (int) $merged['fouls']['max_player'] : null,
            managerLineupDeadlineMinutes: (int) $merged['lineup']['manager_deadline_minutes'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'points' => ['win' => $this->pointsWin, 'draw' => $this->pointsDraw, 'loss' => $this->pointsLoss, 'walkover_loss' => $this->pointsWalkoverLoss],
            'fouls' => ['max_player' => $this->maxPlayerFouls],
            'tiebreakers' => array_map(fn (Tiebreaker $t) => $t->value, $this->tiebreakers),
            'cards' => [
                'yellow_for_suspension' => $this->yellowCardsForSuspension,
                'yellow_suspension_games' => $this->yellowSuspensionGames,
                'red_suspension_games' => $this->redSuspensionGames,
                'reset_yellows_between_stages' => $this->resetYellowsBetweenStages,
                'second_yellow_counts_for_series' => $this->secondYellowCountsForSeries,
            ],
            'walkover' => ['winner_goals' => $this->walkoverWinnerGoals, 'loser_goals' => $this->walkoverLoserGoals],
            'substitutions' => ['max' => $this->maxSubstitutions, 'max_windows' => $this->maxSubstitutionWindows, 'allow_reentry' => $this->allowSubstitutionReentry],
            'lineup' => ['max_starters' => $this->maxStarters, 'max_bench' => $this->maxBench, 'max_staff' => $this->maxStaff, 'manager_deadline_minutes' => $this->managerLineupDeadlineMinutes],
            'scoring' => [
                'model' => $this->scoreModel,
                'sets_to_win' => $this->setsToWin,
                'set_points' => $this->setPoints,
                'deciding_set_points' => $this->decidingSetPoints,
                'min_lead' => $this->setMinLead,
                'points_narrow_win' => $this->pointsNarrowWin,
                'points_narrow_loss' => $this->pointsNarrowLoss,
            ],
        ];
    }
}

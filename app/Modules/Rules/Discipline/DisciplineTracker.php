<?php

namespace App\Modules\Rules\Discipline;

use App\Modules\Rules\RuleSet;

/**
 * Suspensão automática por cartões de UMA equipe, processando seus jogos em ordem.
 *
 * Regras (parametrizadas no {@see RuleSet}), baseadas no regulamento de referência:
 * - N amarelos acumulados = suspensão (padrão: 3 amarelos = 1 jogo); a contagem recomeça após cada suspensão.
 * - Vermelho = suspensão (padrão: 1 jogo). Dois amarelos no mesmo jogo equivalem a uma expulsão e, por padrão,
 *   esses amarelos não entram na contagem da série.
 * - O 3º amarelo somado a um vermelho no mesmo jogo gera as duas suspensões (2 jogos).
 * - Ao mudar de fase, os amarelos acumulados zeram (por padrão); suspensões já geradas continuam.
 * - A suspensão é cumprida nos próximos jogos da equipe; W.O. cumpre, jogo adiado não.
 *
 * Penas do tribunal disciplinar não são tratadas aqui (são lançadas manualmente).
 */
final class DisciplineTracker
{
    /** @var array<string, int> */
    private array $seriesYellows = [];

    /** @var array<string, int> */
    private array $pendingGames = [];

    private ?string $currentStageId = null;

    public function __construct(private readonly RuleSet $rules) {}

    public function apply(TeamGameRecord $game): void
    {
        if ($this->currentStageId !== null
            && $this->currentStageId !== $game->stageId
            && $this->rules->resetYellowsBetweenStages) {
            $this->seriesYellows = [];
        }
        $this->currentStageId = $game->stageId;

        // 1) Este jogo cumpre uma rodada de cada suspensão já pendente.
        if ($game->countsAsServed) {
            foreach ($this->pendingGames as $personId => $games) {
                $this->pendingGames[$personId] = max(0, $games - 1);
            }
            $this->pendingGames = array_filter($this->pendingGames);
        }

        // 2) Cartões recebidos neste jogo geram novas suspensões (valem a partir do próximo jogo).
        foreach ($game->cards as $personId => $cards) {
            $this->registerCards($personId, $cards['yellow'] ?? 0, $cards['red'] ?? 0);
        }
    }

    /**
     * @param  iterable<TeamGameRecord>  $games
     */
    public function applyAll(iterable $games): void
    {
        foreach ($games as $game) {
            $this->apply($game);
        }
    }

    /** Está suspenso para o PRÓXIMO jogo da equipe? */
    public function isSuspended(string $personId): bool
    {
        return ($this->pendingGames[$personId] ?? 0) > 0;
    }

    public function pendingGames(string $personId): int
    {
        return $this->pendingGames[$personId] ?? 0;
    }

    /** Amarelos acumulados na série atual (ainda sem suspensão). */
    public function seriesYellows(string $personId): int
    {
        return $this->seriesYellows[$personId] ?? 0;
    }

    /**
     * @return array<string, int> pessoa => jogos de suspensão restantes
     */
    public function suspended(): array
    {
        return $this->pendingGames;
    }

    private function registerCards(string $personId, int $yellow, int $red): void
    {
        $expelled = $red > 0 || $yellow >= 2;
        $countedYellows = ($yellow >= 2 && ! $this->rules->secondYellowCountsForSeries) ? 0 : $yellow;

        $this->seriesYellows[$personId] = ($this->seriesYellows[$personId] ?? 0) + $countedYellows;

        $suspension = 0;
        while ($this->seriesYellows[$personId] >= $this->rules->yellowCardsForSuspension) {
            $this->seriesYellows[$personId] -= $this->rules->yellowCardsForSuspension;
            $suspension += $this->rules->yellowSuspensionGames;
        }

        if ($expelled) {
            $suspension += $this->rules->redSuspensionGames;
        }

        if ($suspension > 0) {
            $this->pendingGames[$personId] = ($this->pendingGames[$personId] ?? 0) + $suspension;
        }
    }
}

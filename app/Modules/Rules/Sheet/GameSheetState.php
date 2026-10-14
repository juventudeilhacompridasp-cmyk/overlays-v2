<?php

namespace App\Modules\Rules\Sheet;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Rules\RuleSet;

/**
 * Estado de uma súmula de futebol e validação dos lançamentos. Código puro, sem banco.
 *
 * Regras (parametrizadas no {@see RuleSet}):
 * - gols, gols contra e substituições só envolvem quem está em campo; cartões podem ir a qualquer um da escalação
 *   (titulares, reservas e comissão) que não tenha sido expulso;
 * - expulso (vermelho direto ou segundo amarelo) sai de campo e não recebe mais lançamentos;
 * - substituição: sai quem está em campo e entra um reserva que ainda não jogou; quem sai não volta;
 * - limite de substituições e de "atos" de substituição (momentos distintos em que houve troca).
 */
final class GameSheetState
{
    /** @var array<string, list<string>> */
    private array $onField = [];

    /** @var array<string, list<string>> */
    private array $bench = [];

    /** @var array<string, list<string>> */
    private array $staff = [];

    /** @var array<string, array<string, int>> */
    private array $yellows = [];

    /** @var array<string, array<string, true>> */
    private array $expelled = [];

    /** @var array<string, array<string, int>> faltas pessoais */
    private array $fouls = [];

    /** @var array<string, array<string, true>> eliminados por faltas (ainda em quadra até a troca) */
    private array $fouledOut = [];

    /** @var array<string, array<string, int>> faltas técnicas + antidesportivas (a segunda desqualifica) */
    private array $disqualifying = [];

    /** @var array<string, int> */
    private array $substitutions = [];

    /** @var array<string, array<int, int>> */
    private array $substitutionsByPeriod = [];

    /** @var array<string, array<string, true>> */
    private array $windows = [];

    /** @var array<string, array<string, true>> pessoas que já entraram ou saíram (não voltam a ser reservas) */
    private array $used = [];

    public function __construct(private readonly RuleSet $rules) {}

    /**
     * @param  list<string>  $starters
     * @param  list<string>  $bench
     * @param  list<string>  $staff
     */
    public function setLineup(string $teamId, array $starters, array $bench = [], array $staff = []): void
    {
        $this->onField[$teamId] = $starters;
        $this->bench[$teamId] = $bench;
        $this->staff[$teamId] = $staff;
        $this->yellows[$teamId] ??= [];
        $this->expelled[$teamId] ??= [];
        $this->fouls[$teamId] ??= [];
        $this->fouledOut[$teamId] ??= [];
        $this->disqualifying[$teamId] ??= [];
        $this->substitutions[$teamId] ??= 0;
        $this->substitutionsByPeriod[$teamId] ??= [];
        $this->windows[$teamId] ??= [];
        $this->used[$teamId] ??= [];
    }

    /**
     * Mensagem de erro (em português) se o lançamento não for permitido; null se for válido.
     */
    public function check(SheetEvent $event): ?string
    {
        $team = $event->teamId;

        if (! isset($this->onField[$team]) && $event->type !== GameEventType::Timeout) {
            return 'A equipe ainda não tem escalação neste jogo.';
        }

        return match ($event->type) {
            GameEventType::Goal, GameEventType::OwnGoal => $this->checkOnField($event, 'marcar gol'),
            GameEventType::FreeThrow, GameEventType::FieldGoal, GameEventType::ThreePointer => $this->checkOnField($event, 'pontuar') ?? $this->checkNotFouledOut($event),
            GameEventType::Foul, GameEventType::TechnicalFoul, GameEventType::UnsportsmanlikeFoul => $this->checkInLineup($event) ?? $this->checkNotFouledOut($event),
            GameEventType::YellowCard, GameEventType::RedCard, GameEventType::BlueCard, GameEventType::Warning => $this->checkInLineup($event),
            GameEventType::Substitution => $this->checkSubstitution($event),
            GameEventType::PenaltyKick => $this->checkOnField($event, 'cobrar pênalti'),
            GameEventType::PeriodStart, GameEventType::PeriodEnd, GameEventType::Point, GameEventType::ClockPause, GameEventType::ClockResume, GameEventType::Timeout, GameEventType::ShotClockReset => null,
        };
    }

    /**
     * Aplica um lançamento já validado (atualiza quem está em campo, expulsos, substituições...).
     */
    public function apply(SheetEvent $event): void
    {
        $team = $event->teamId;
        $person = $event->personId;

        switch ($event->type) {
            case GameEventType::YellowCard:
                $this->yellows[$team][$person] = ($this->yellows[$team][$person] ?? 0) + 1;

                if ($this->yellows[$team][$person] >= 2) {
                    $this->expel($team, (string) $person);
                }
                break;

            case GameEventType::RedCard:
                $this->expel($team, (string) $person);
                break;

            case GameEventType::Foul:
            case GameEventType::TechnicalFoul:
            case GameEventType::UnsportsmanlikeFoul:
                $this->fouls[$team][$person] = ($this->fouls[$team][$person] ?? 0) + 1;

                // Basquete: a falta que completa o limite elimina o atleta (ele segue em quadra até ser substituído).
                if ($this->rules->maxPlayerFouls !== null && $this->fouls[$team][$person] >= $this->rules->maxPlayerFouls) {
                    $this->fouledOut[$team][(string) $person] = true;
                }

                // Segunda falta técnica/antidesportiva (em qualquer combinação) desqualifica: sai na hora e não volta.
                if ($event->type->isDisqualifying()) {
                    $this->disqualifying[$team][$person] = ($this->disqualifying[$team][$person] ?? 0) + 1;

                    if ($this->disqualifying[$team][$person] >= $this->rules->maxDisqualifyingFouls) {
                        $this->expel($team, (string) $person);
                    }
                }
                break;

            case GameEventType::Substitution:
                $this->onField[$team] = array_values(array_diff($this->onField[$team], [(string) $person]));
                $this->bench[$team] = array_values(array_diff($this->bench[$team], [(string) $event->secondaryPersonId]));
                $this->onField[$team][] = (string) $event->secondaryPersonId;

                // Esportes com substituição volante (futsal): quem saiu volta a ser reserva e pode entrar de novo.
                if ($this->rules->allowSubstitutionReentry) {
                    $this->bench[$team][] = (string) $person;
                }

                $this->substitutions[$team]++;
                $this->substitutionsByPeriod[$team][$event->period] = ($this->substitutionsByPeriod[$team][$event->period] ?? 0) + 1;
                $this->windows[$team][$this->windowKey($event)] = true;
                $this->used[$team][(string) $person] = true;
                $this->used[$team][(string) $event->secondaryPersonId] = true;
                break;

            default:
                break;
        }
    }

    /** Valida e aplica; retorna a mensagem de erro (e não altera o estado) ou null. */
    public function tryApply(SheetEvent $event): ?string
    {
        $error = $this->check($event);

        if ($error === null) {
            $this->apply($event);
        }

        return $error;
    }

    /** @return list<string> */
    public function onField(string $teamId): array
    {
        return $this->onField[$teamId] ?? [];
    }

    /** @return list<string> Reservas que ainda podem entrar. */
    public function bench(string $teamId): array
    {
        return $this->bench[$teamId] ?? [];
    }

    /**
     * Faltas pessoais por atleta.
     *
     * @return array<string, int>
     */
    public function foulsOf(string $teamId): array
    {
        return $this->fouls[$teamId] ?? [];
    }

    /**
     * Cartões amarelos por pessoa no jogo.
     *
     * @return array<string, int>
     */
    public function yellowsOf(string $teamId): array
    {
        return $this->yellows[$teamId] ?? [];
    }

    /**
     * Eliminados por faltas (basquete) que ainda estão em quadra.
     *
     * @return list<string>
     */
    public function fouledOut(string $teamId): array
    {
        return array_map('strval', array_keys($this->fouledOut[$teamId] ?? []));
    }

    /** @return list<string> */
    public function expelled(string $teamId): array
    {
        return array_keys($this->expelled[$teamId] ?? []);
    }

    public function substitutionsUsed(string $teamId): int
    {
        return $this->substitutions[$teamId] ?? 0;
    }

    public function substitutionsUsedInPeriod(string $teamId, int $period): int
    {
        return $this->substitutionsByPeriod[$teamId][$period] ?? 0;
    }

    public function windowsUsed(string $teamId): int
    {
        return count($this->windows[$teamId] ?? []);
    }

    private function checkOnField(SheetEvent $event, string $action): ?string
    {
        if ($event->personId === null) {
            return 'Informe o atleta.';
        }

        if (isset($this->expelled[$event->teamId][$event->personId])) {
            return 'O atleta foi expulso e não pode receber novos lançamentos.';
        }

        if (! in_array($event->personId, $this->onField[$event->teamId], true)) {
            return "Só quem está em campo pode {$action}.";
        }

        return null;
    }

    private function checkInLineup(SheetEvent $event): ?string
    {
        if ($event->personId === null) {
            return 'Informe a pessoa.';
        }

        if (isset($this->expelled[$event->teamId][$event->personId])) {
            return 'Esta pessoa já foi expulsa e não pode receber novos lançamentos.';
        }

        $inLineup = in_array($event->personId, $this->onField[$event->teamId], true)
            || in_array($event->personId, $this->bench[$event->teamId], true)
            || in_array($event->personId, $this->staff[$event->teamId], true)
            || isset($this->used[$event->teamId][$event->personId]);

        return $inLineup ? null : 'Esta pessoa não está na escalação do jogo.';
    }

    private function checkNotFouledOut(SheetEvent $event): ?string
    {
        return isset($this->fouledOut[$event->teamId][(string) $event->personId]) ? 'O atleta foi eliminado por faltas.' : null;
    }

    private function checkSubstitution(SheetEvent $event): ?string
    {
        $team = $event->teamId;
        $out = $event->personId;
        $in = $event->secondaryPersonId;

        if ($out === null || $in === null) {
            return 'Informe quem sai e quem entra.';
        }

        if ($out === $in) {
            return 'Quem sai e quem entra precisam ser pessoas diferentes.';
        }

        if (! in_array($out, $this->onField[$team], true)) {
            return 'Quem sai precisa estar em campo.';
        }

        if (! $this->rules->allowSubstitutionReentry && isset($this->used[$team][$in])) {
            return 'Quem já jogou e saiu não pode voltar ao jogo.';
        }

        if (isset($this->fouledOut[$team][$in])) {
            return 'O atleta foi eliminado por faltas e não pode entrar.';
        }

        if (! in_array($in, $this->bench[$team], true)) {
            return 'Quem entra precisa estar entre os reservas da escalação.';
        }

        if ($this->substitutions[$team] >= $this->rules->maxSubstitutions) {
            return "Limite de {$this->rules->maxSubstitutions} substituições por jogo atingido.";
        }

        if ($this->rules->maxSubstitutionsPerPeriod !== null && ($this->substitutionsByPeriod[$team][$event->period] ?? 0) >= $this->rules->maxSubstitutionsPerPeriod) {
            return "Limite de {$this->rules->maxSubstitutionsPerPeriod} substituições neste período atingido.";
        }

        $key = $this->windowKey($event);

        if (! isset($this->windows[$team][$key]) && count($this->windows[$team]) >= $this->rules->maxSubstitutionWindows) {
            return "Limite de {$this->rules->maxSubstitutionWindows} atos de substituição atingido.";
        }

        return null;
    }

    private function expel(string $team, string $person): void
    {
        $this->expelled[$team][$person] = true;
        $this->onField[$team] = array_values(array_diff($this->onField[$team], [$person]));
    }

    private function windowKey(SheetEvent $event): string
    {
        return $event->period.':'.($event->minute ?? 'x');
    }
}

<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Scheduling\KnockoutBracket;
use InvalidArgumentException;

/**
 * Cria a tabela de uma fase a partir de um sorteio JÁ DEFINIDO (feito na tela arrastando equipes, ou sorteio externo):
 * grupos com suas equipes (fase de grupos) ou os confrontos da 1ª rodada (mata-mata). Todas as equipes confirmadas da
 * divisão precisam entrar, cada uma uma única vez; qualquer problema recusa tudo.
 */
final class DrawStage
{
    public function __construct(private readonly GenerateStageFixtures $generate) {}

    /**
     * @param  list<array{name: string, entries: list<string>}>  $groups
     * @return int Quantidade de jogos criados.
     */
    public function groups(Stage $stage, array $groups, bool $doubleRound): int
    {
        $this->ensureReady($stage, StageType::Groups);

        $names = [];
        $placed = [];

        foreach ($groups as ['name' => $name, 'entries' => $entries]) {
            $key = mb_strtolower(trim($name));

            if ($key === '' || isset($names[$key])) {
                throw new InvalidArgumentException('Cada grupo precisa de um nome diferente.');
            }

            $names[$key] = true;

            if (count($entries) < 2) {
                throw new InvalidArgumentException("O grupo “{$name}” precisa de pelo menos duas equipes.");
            }

            array_push($placed, ...$entries);
        }

        $this->ensureEveryTeamOnce($stage, $placed);

        return $this->generate->persistGroups($stage, array_map(fn (array $group) => ['name' => trim($group['name']), 'entries' => $group['entries']], $groups), $doubleRound);
    }

    /**
     * Cria uma tabela de pontos corridos a partir dos confrontos arrastados pelo organizador.
     * Cada par de equipes precisa aparecer exatamente uma vez (ou duas, no ida e volta).
     *
     * @param  list<array{0: ?string, 1: ?string}>  $pairs
     */
    public function league(Stage $stage, array $pairs, bool $doubleRound): int
    {
        $this->ensureReady($stage, StageType::League);

        $confirmed = Entry::query()->where('division_id', $stage->division_id)->where('status', EntryStatus::Confirmed)->pluck('id')->all();
        $expected = count($confirmed) * (count($confirmed) - 1) / 2 * ($doubleRound ? 2 : 1);

        if (count($confirmed) < 2 || count($pairs) !== $expected) {
            throw new InvalidArgumentException('A tabela precisa ter todos os confrontos entre as equipes confirmadas.');
        }

        $confirmedSet = array_fill_keys($confirmed, true);
        $pairCounts = [];
        $appearances = array_fill_keys($confirmed, 0);

        foreach ($pairs as [$home, $away]) {
            if ($home === null || $away === null || $home === $away || ! isset($confirmedSet[$home]) || ! isset($confirmedSet[$away])) {
                throw new InvalidArgumentException('Há um confronto inválido ou uma equipe que não está confirmada nesta divisão.');
            }

            $key = collect([$home, $away])->sort()->implode('|');
            $pairCounts[$key] = ($pairCounts[$key] ?? 0) + 1;
            $appearances[$home]++;
            $appearances[$away]++;
        }

        if (count($pairCounts) !== count($confirmed) * (count($confirmed) - 1) / 2 || max($pairCounts) !== ($doubleRound ? 2 : 1) || min($pairCounts) !== ($doubleRound ? 2 : 1)) {
            throw new InvalidArgumentException('Cada par de equipes precisa aparecer uma vez'.($doubleRound ? ' por turno.' : '.'));
        }

        $expectedAppearances = ($doubleRound ? 2 : 1) * (count($confirmed) - 1);
        if (in_array($expectedAppearances, $appearances, true) === false) {
            throw new InvalidArgumentException('Todas as equipes precisam ter o mesmo número de confrontos.');
        }

        return $this->generate->persistLeague($stage, array_map(fn (array $pair) => [(string) $pair[0], (string) $pair[1]], $pairs), max(1, intdiv(count($confirmed), 2)));
    }

    /**
     * @param  list<array{0: ?string, 1: ?string}>  $pairs  confrontos da 1ª rodada, na ordem do chaveamento
     * @param  bool  $partial  só parte das equipes confirmadas entra na chave (ex.: os classificados da fase anterior);
     *                         as demais ficam de fora, mas cada equipe colocada ainda precisa ser confirmada e única
     * @return int Quantidade de jogos criados.
     */
    public function knockout(Stage $stage, array $pairs, int $legs, bool $partial = false): int
    {
        $this->ensureReady($stage, StageType::Knockout);

        $placed = [];
        foreach ($pairs as $pair) {
            foreach ($pair as $entryId) {
                if ($entryId !== null) {
                    $placed[] = $entryId;
                }
            }
        }

        if ($partial) {
            $this->ensureSubset($stage, $placed);
        } else {
            $this->ensureEveryTeamOnce($stage, $placed);
        }

        return $this->generate->persistKnockout($stage, KnockoutBracket::fromPairs($pairs), $legs);
    }

    private function ensureReady(Stage $stage, StageType $type): void
    {
        if ($stage->type !== $type) {
            throw new InvalidArgumentException($type === StageType::Groups ? 'Esta fase não é de grupos.' : 'Esta fase não é de mata-mata.');
        }

        if ($stage->games()->exists()) {
            throw new InvalidArgumentException('Esta fase já tem jogos. Limpe a tabela antes de sortear de novo.');
        }
    }

    /** @param  list<string>  $placed */
    private function ensureSubset(Stage $stage, array $placed): void
    {
        if (count($placed) !== count(array_unique($placed))) {
            throw new InvalidArgumentException('Uma equipe aparece mais de uma vez no sorteio.');
        }

        if (count($placed) < 2) {
            throw new InvalidArgumentException('Coloque pelo menos duas equipes na chave.');
        }

        $confirmed = Entry::query()->where('division_id', $stage->division_id)->where('status', EntryStatus::Confirmed)->pluck('id')->all();

        if (array_diff($placed, $confirmed) !== []) {
            throw new InvalidArgumentException('Há equipes que não estão confirmadas nesta divisão.');
        }
    }

    /** @param  list<string>  $placed */
    private function ensureEveryTeamOnce(Stage $stage, array $placed): void
    {
        $confirmed = Entry::query()->where('division_id', $stage->division_id)->where('status', EntryStatus::Confirmed)->pluck('id')->all();

        if (count($placed) !== count(array_unique($placed))) {
            throw new InvalidArgumentException('Uma equipe aparece mais de uma vez no sorteio.');
        }

        if (array_diff($placed, $confirmed) !== []) {
            throw new InvalidArgumentException('Há equipes que não estão confirmadas nesta divisão.');
        }

        if (array_diff($confirmed, $placed) !== []) {
            throw new InvalidArgumentException('Todas as equipes confirmadas da divisão precisam entrar no sorteio.');
        }

        if (count($confirmed) < 2) {
            throw new InvalidArgumentException('Confirme pelo menos duas equipes na divisão.');
        }
    }
}

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

        return $this->generate->persistGroups($stage, array_map(fn (array $group) => ['name' => trim($group['name']), 'entries' => array_values($group['entries'])], $groups), $doubleRound);
    }

    /**
     * @param  list<array{0: ?string, 1: ?string}>  $pairs  confrontos da 1ª rodada, na ordem do chaveamento
     * @return int Quantidade de jogos criados.
     */
    public function knockout(Stage $stage, array $pairs, int $legs): int
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

        $this->ensureEveryTeamOnce($stage, $placed);

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

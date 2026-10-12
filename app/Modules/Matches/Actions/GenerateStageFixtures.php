<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Scheduling\RoundRobin;
use App\Modules\Matches\Scheduling\SnakeDistributor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gera a tabela de jogos de uma fase a partir das equipes inscritas e confirmadas na divisão
 * (na ordem de cabeça de chave): pontos corridos ou fase de grupos (todos contra todos em cada grupo).
 * Os jogos nascem sem data e sem estádio; o organizador agenda depois.
 */
final class GenerateStageFixtures
{
    private const MAX_GROUPS = 16;

    /**
     * @return int Quantidade de jogos criados.
     */
    public function handle(Stage $stage, bool $doubleRound = false, ?int $groups = null): int
    {
        if ($stage->games()->exists()) {
            throw new InvalidArgumentException('Esta fase já tem jogos. Limpe a tabela antes de gerar de novo.');
        }

        $entryIds = Entry::query()
            ->where('division_id', $stage->division_id)
            ->where('status', EntryStatus::Confirmed)
            ->inDrawOrder()
            ->pluck('id')
            ->all();

        return match ($stage->type) {
            StageType::League => $this->league($stage, $entryIds, $doubleRound),
            StageType::Groups => $this->groups($stage, $entryIds, $doubleRound, $groups),
            StageType::Knockout => throw new InvalidArgumentException('A geração do mata-mata chega em uma próxima etapa.'),
        };
    }

    /**
     * @param  list<string>  $entryIds
     */
    private function league(Stage $stage, array $entryIds, bool $doubleRound): int
    {
        if (count($entryIds) < 2) {
            throw new InvalidArgumentException('Confirme pelo menos duas equipes na divisão para gerar a tabela.');
        }

        return DB::transaction(function () use ($stage, $entryIds, $doubleRound): int {
            $number = 1;

            foreach (RoundRobin::rounds($entryIds, $doubleRound) as $index => $round) {
                foreach ($round as [$home, $away]) {
                    $this->createGame($stage, null, $index + 1, $number++, $home, $away);
                }
            }

            return $number - 1;
        });
    }

    /**
     * @param  list<string>  $entryIds
     */
    private function groups(Stage $stage, array $entryIds, bool $doubleRound, ?int $groups): int
    {
        $groups ??= 1;

        if ($groups < 1 || $groups > self::MAX_GROUPS) {
            throw new InvalidArgumentException('Informe de 1 a '.self::MAX_GROUPS.' grupos.');
        }

        if (count($entryIds) < $groups * 2) {
            throw new InvalidArgumentException('Cada grupo precisa de pelo menos duas equipes confirmadas.');
        }

        return DB::transaction(function () use ($stage, $entryIds, $doubleRound, $groups): int {
            $number = 1;
            $roundsByGroup = [];

            foreach (SnakeDistributor::distribute($entryIds, $groups) as $index => $members) {
                $group = Group::query()->create([
                    'organization_id' => $stage->organization_id,
                    'stage_id' => $stage->getKey(),
                    'name' => 'Grupo '.$this->groupLabel($index),
                    'sequence' => $index + 1,
                ]);
                $group->entries()->attach($members);

                $roundsByGroup[$group->getKey()] = RoundRobin::rounds($members, $doubleRound);
            }

            // Numera os jogos rodada a rodada, grupo a grupo, para a tabela ficar na ordem em que acontece.
            $maxRounds = max(array_map('count', $roundsByGroup));

            for ($round = 0; $round < $maxRounds; $round++) {
                foreach ($roundsByGroup as $groupId => $rounds) {
                    foreach ($rounds[$round] ?? [] as [$home, $away]) {
                        $this->createGame($stage, $groupId, $round + 1, $number++, $home, $away);
                    }
                }
            }

            return $number - 1;
        });
    }

    private function createGame(Stage $stage, ?string $groupId, int $round, int $number, string $home, string $away): void
    {
        Game::query()->create([
            'organization_id' => $stage->organization_id,
            'division_id' => $stage->division_id,
            'stage_id' => $stage->getKey(),
            'group_id' => $groupId,
            'round' => $round,
            'number' => $number,
            'home_entry_id' => $home,
            'away_entry_id' => $away,
        ]);
    }

    private function groupLabel(int $index): string
    {
        return $index < 26 ? chr(ord('A') + $index) : (string) ($index + 1);
    }
}

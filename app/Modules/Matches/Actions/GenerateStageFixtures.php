<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Scheduling\KnockoutBracket;
use App\Modules\Matches\Scheduling\KnockoutTie;
use App\Modules\Matches\Scheduling\RoundRobin;
use App\Modules\Matches\Scheduling\SnakeDistributor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    public function handle(Stage $stage, bool $doubleRound = false, ?int $groups = null, int $legs = 1): int
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
            StageType::Knockout => $this->knockout($stage, $entryIds, $legs),
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

        $named = [];
        foreach (SnakeDistributor::distribute($entryIds, $groups) as $index => $members) {
            $named[] = ['name' => 'Grupo '.$this->groupLabel($index), 'entries' => $members];
        }

        return $this->persistGroups($stage, $named, $doubleRound);
    }

    /**
     * Cria os grupos informados (nome + equipes) e a tabela todos contra todos de cada um, numerada rodada a rodada, grupo
     * a grupo. Também é o caminho do sorteio montado à mão (`DrawStage`).
     *
     * @param  list<array{name: string, entries: list<string>}>  $groups
     */
    public function persistGroups(Stage $stage, array $groups, bool $doubleRound): int
    {
        return DB::transaction(function () use ($stage, $groups, $doubleRound): int {
            $number = 1;
            $roundsByGroup = [];

            foreach ($groups as $index => ['name' => $name, 'entries' => $members]) {
                $group = Group::query()->create([
                    'organization_id' => $stage->organization_id,
                    'stage_id' => $stage->getKey(),
                    'name' => $name,
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

    /**
     * Persiste uma tabela de pontos corridos já montada pelo organizador.
     *
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    public function persistLeague(Stage $stage, array $pairs, int $gamesPerRound): int
    {
        return DB::transaction(function () use ($stage, $pairs, $gamesPerRound): int {
            foreach ($pairs as $index => [$home, $away]) {
                $this->createGame($stage, null, intdiv($index, max(1, $gamesPerRound)) + 1, $index + 1, $home, $away);
            }

            return count($pairs);
        });
    }

    /**
     * Chaveamento do mata-mata: um jogo (ou ida e volta) por confronto, na ordem das rodadas. Quem folga
     * na 1ª rodada já nasce na 2ª; os demais lados ficam em aberto até o vencedor da rodada anterior ser conhecido.
     *
     * @param  list<string>  $entryIds
     */
    private function knockout(Stage $stage, array $entryIds, int $legs): int
    {
        if (! in_array($legs, [1, 2], true)) {
            throw new InvalidArgumentException('O confronto pode ter um jogo ou ida e volta.');
        }

        if (count($entryIds) < 2) {
            throw new InvalidArgumentException('Confirme pelo menos duas equipes na divisão para gerar o mata-mata.');
        }

        return $this->persistKnockout($stage, KnockoutBracket::build($entryIds), $legs);
    }

    /**
     * Cria os jogos de um chaveamento já montado (`KnockoutBracket::build` ou `fromPairs`): um jogo (ou ida e volta) por
     * confronto, encadeados pelo `tie_id` até a final.
     *
     * @param  list<KnockoutTie>  $ties
     */
    public function persistKnockout(Stage $stage, array $ties, int $legs): int
    {
        if (! in_array($legs, [1, 2], true)) {
            throw new InvalidArgumentException('O confronto pode ter um jogo ou ida e volta.');
        }

        return DB::transaction(function () use ($stage, $ties, $legs): int {
            $tieIds = [];

            foreach ($ties as $tie) {
                if (! $tie->bye) {
                    $tieIds[$tie->round][$tie->position] = (string) Str::ulid();
                }
            }

            $number = 1;

            foreach ($ties as $tie) {
                if ($tie->bye) {
                    continue;
                }

                $nextTieId = $tie->nextPosition === null ? null : $tieIds[$tie->round + 1][$tie->nextPosition];

                for ($leg = 1; $leg <= $legs; $leg++) {
                    // Ida e volta: na ida o mandante é o lado b; na volta, o lado a.
                    [$home, $away] = $legs === 1 || $leg === 2 ? [$tie->a, $tie->b] : [$tie->b, $tie->a];

                    Game::query()->create([
                        'organization_id' => $stage->organization_id,
                        'division_id' => $stage->division_id,
                        'stage_id' => $stage->getKey(),
                        'round' => $tie->round,
                        'number' => $number++,
                        'home_entry_id' => $home,
                        'away_entry_id' => $away,
                        'tie_id' => $tieIds[$tie->round][$tie->position],
                        'leg' => $leg,
                        'next_tie_id' => $nextTieId,
                        'next_slot' => $tie->nextSlot,
                    ]);
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

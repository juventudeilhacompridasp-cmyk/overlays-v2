<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\Venue;
use App\Modules\Matches\Scheduling\FixtureSheet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cria a tabela de jogos de uma fase a partir de confrontos já definidos (sem sorteio): pontos corridos ou fase de grupos.
 * Tudo ou nada: qualquer linha com problema (equipe que não está confirmada na divisão, estádio desconhecido, equipe
 * contra ela mesma, grupo faltando...) recusa a importação inteira e devolve a lista de problemas.
 */
final class ImportStageFixtures
{
    private const MAX_GAMES = 1000;

    /**
     * @return int Quantidade de jogos criados.
     */
    public function handle(Stage $stage, string $text): int
    {
        if ($stage->type === StageType::Knockout) {
            throw new InvalidArgumentException('A importação vale para pontos corridos e fase de grupos. O mata-mata é montado pelo chaveamento.');
        }

        if ($stage->games()->exists()) {
            throw new InvalidArgumentException('Esta fase já tem jogos. Limpe a tabela antes de importar.');
        }

        $parsed = FixtureSheet::parse($text);
        $errors = $parsed['errors'];
        $rows = $parsed['rows'];

        if ($rows === [] && $errors === []) {
            throw new InvalidArgumentException('Cole pelo menos um jogo (rodada; mandante; visitante; data; hora; local; grupo).');
        }

        if (count($rows) > self::MAX_GAMES) {
            throw new InvalidArgumentException('Importe no máximo '.self::MAX_GAMES.' jogos por vez.');
        }

        $entries = [];
        foreach (Entry::query()->with('team')->where('division_id', $stage->division_id)->where('status', EntryStatus::Confirmed)->get() as $entry) {
            foreach (array_filter([$entry->team->name, $entry->team->short_name]) as $name) {
                $entries[FixtureSheet::key($name)] ??= $entry;
            }
        }

        $venues = [];
        foreach (Venue::query()->get(['id', 'name']) as $venue) {
            $venues[FixtureSheet::key($venue->name)] = $venue->id;
        }

        $isGroups = $stage->type === StageType::Groups;
        $games = [];
        $groupOfEntry = [];
        $groupNames = [];

        foreach ($rows as $row) {
            $home = $entries[FixtureSheet::key($row['home'])] ?? null;
            $away = $entries[FixtureSheet::key($row['away'])] ?? null;
            $line = $row['line'];

            if ($home === null) {
                $errors[] = "Linha {$line}: a equipe “{$row['home']}” não está confirmada nesta divisão.";
            }
            if ($away === null) {
                $errors[] = "Linha {$line}: a equipe “{$row['away']}” não está confirmada nesta divisão.";
            }
            if ($home === null || $away === null) {
                continue;
            }
            if ($home->getKey() === $away->getKey()) {
                $errors[] = "Linha {$line}: uma equipe não pode jogar contra ela mesma.";

                continue;
            }

            $venueId = null;
            if ($row['venue'] !== '') {
                $venueId = $venues[FixtureSheet::key($row['venue'])] ?? null;
                if ($venueId === null) {
                    $errors[] = "Linha {$line}: o local “{$row['venue']}” não está cadastrado em Estádios.";

                    continue;
                }
            }

            $groupKey = null;
            if ($isGroups) {
                if ($row['group'] === '') {
                    $errors[] = "Linha {$line}: informe o grupo (esta é uma fase de grupos).";

                    continue;
                }

                $groupKey = FixtureSheet::key($row['group']);
                $groupNames[$groupKey] ??= $row['group'];

                foreach ([$home, $away] as $entry) {
                    $previous = $groupOfEntry[$entry->getKey()] ?? null;
                    if ($previous !== null && $previous !== $groupKey) {
                        $errors[] = "Linha {$line}: “{$entry->team->name}” aparece em dois grupos diferentes.";

                        continue 2;
                    }
                    $groupOfEntry[$entry->getKey()] = $groupKey;
                }
            }

            $games[] = [
                'round' => $row['round'],
                'home' => $home->getKey(),
                'away' => $away->getKey(),
                'venue_id' => $venueId,
                'scheduled_at' => $row['date'] === null ? null : $row['date'].' '.$row['time'].':00',
                'group' => $groupKey,
            ];
        }

        if ($errors !== []) {
            throw new InvalidArgumentException("Nada foi importado. Corrija e envie de novo:\n".implode("\n", array_slice($errors, 0, 15)).(count($errors) > 15 ? "\n... e mais ".(count($errors) - 15).' problema(s).' : ''));
        }

        return DB::transaction(function () use ($stage, $games, $groupNames, $groupOfEntry): int {
            /** @var array<string, string> $groupIds */
            $groupIds = [];
            $sequence = 1;

            foreach ($groupNames as $key => $name) {
                $group = Group::query()->create([
                    'organization_id' => $stage->organization_id,
                    'stage_id' => $stage->getKey(),
                    'name' => $name,
                    'sequence' => $sequence++,
                ]);

                $members = array_keys(array_filter($groupOfEntry, fn (string $groupKey) => $groupKey === $key));
                $group->entries()->attach($members);
                $groupIds[$key] = $group->getKey();
            }

            foreach ($games as $index => $game) {
                Game::query()->create([
                    'organization_id' => $stage->organization_id,
                    'division_id' => $stage->division_id,
                    'stage_id' => $stage->getKey(),
                    'group_id' => $game['group'] === null ? null : $groupIds[$game['group']],
                    'round' => $game['round'],
                    'number' => $index + 1,
                    'home_entry_id' => $game['home'],
                    'away_entry_id' => $game['away'],
                    'venue_id' => $game['venue_id'],
                    'scheduled_at' => $game['scheduled_at'],
                ]);
            }

            return count($games);
        });
    }
}

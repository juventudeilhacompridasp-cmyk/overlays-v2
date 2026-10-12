<?php

namespace App\Modules\Matches\Scheduling;

use InvalidArgumentException;

/**
 * Distribui equipes em grupos em "serpentina" (1º ao grupo A, 2º ao B, ..., depois volta: último ao grupo
 * final, etc.), de modo que os cabeças de chave fiquem em grupos diferentes e os grupos fiquem equilibrados.
 */
final class SnakeDistributor
{
    /**
     * @param  list<string>  $teamIds  Na ordem de cabeça de chave.
     * @return list<list<string>> Um array por grupo (os grupos nunca ficam vazios).
     */
    public static function distribute(array $teamIds, int $groups): array
    {
        $teamIds = array_values($teamIds);

        if ($groups < 1) {
            throw new InvalidArgumentException('É preciso pelo menos um grupo.');
        }

        if ($groups > count($teamIds)) {
            throw new InvalidArgumentException('Há mais grupos do que equipes.');
        }

        $result = array_fill(0, $groups, []);

        foreach ($teamIds as $index => $teamId) {
            $cycle = intdiv($index, $groups);
            $position = $index % $groups;
            $group = $cycle % 2 === 0 ? $position : $groups - 1 - $position;

            $result[$group][] = $teamId;
        }

        return $result;
    }
}

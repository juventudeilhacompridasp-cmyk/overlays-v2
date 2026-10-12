<?php

namespace App\Modules\Competitions\Data;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Rules\Standings\StandingRow;

/**
 * Uma linha da classificação já ligada à inscrição (e, por ela, à equipe).
 */
final readonly class StandingEntry
{
    public function __construct(
        public StandingRow $row,
        public Entry $entry,
    ) {}
}

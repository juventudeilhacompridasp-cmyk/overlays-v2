<?php

namespace App\Modules\Teams\Models;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\RosterEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[UseFactory(RosterEntryFactory::class)]
class RosterEntry extends Model
{
    /** @use HasFactory<RosterEntryFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $table = 'roster_entries';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'role' => RosterRole::class,
            'status' => RosterStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}

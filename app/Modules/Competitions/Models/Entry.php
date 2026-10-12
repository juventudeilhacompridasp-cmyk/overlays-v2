<?php

namespace App\Modules\Competitions\Models;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property EntryStatus $status
 */
#[Unguarded]
#[UseFactory(EntryFactory::class)]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => EntryStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'entry_group');
    }

    /**
     * @return HasMany<RosterEntry, $this>
     */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }
}

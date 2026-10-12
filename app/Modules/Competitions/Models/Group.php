<?php

namespace App\Modules\Competitions\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Unguarded]
#[UseFactory(GroupFactory::class)]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * @return BelongsToMany<Entry, $this>
     */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_group');
    }
}

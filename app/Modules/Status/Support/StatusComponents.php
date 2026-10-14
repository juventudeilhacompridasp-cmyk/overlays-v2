<?php

namespace App\Modules\Status\Support;

use App\Modules\Status\Models\Component;
use App\Modules\Status\Probes\DatabaseProbe;
use App\Modules\Status\Probes\Probe;
use App\Modules\Status\Probes\QueueProbe;
use App\Modules\Status\Probes\RealtimeProbe;
use App\Modules\Status\Probes\StorageProbe;
use App\Modules\Status\Probes\WebProbe;
use Illuminate\Database\Eloquent\Collection;

/** Catálogo dos componentes monitorados e das sondas de cada um. */
class StatusComponents
{
    /** @return list<Probe> */
    public static function probes(): array
    {
        return [new WebProbe, new RealtimeProbe, new DatabaseProbe, new QueueProbe, new StorageProbe];
    }

    /**
     * Garante os componentes no banco (idempotente) e os devolve na ordem de exibição.
     *
     * @return Collection<int, Component>
     */
    public static function ensure(): Collection
    {
        foreach (self::probes() as $index => $probe) {
            Component::query()->firstOrCreate(['key' => $probe->key()], ['name' => $probe->name(), 'sort' => $index]);
        }

        return Component::query()->orderBy('sort')->get();
    }
}

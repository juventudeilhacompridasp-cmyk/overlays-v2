<?php

namespace App\Modules\Status\Probes;

use Illuminate\Support\Facades\Storage;
use Throwable;

/** Armazenamento de arquivos (S3/R2) alcançável: consulta a um objeto de teste (existir ou não não importa). */
class StorageProbe implements Probe
{
    public function key(): string
    {
        return 'storage';
    }

    public function name(): string
    {
        return 'Armazenamento de arquivos';
    }

    public function run(): ProbeResult
    {
        $disk = (string) config('platform.status.storage_disk');

        if ($disk === '' || config("filesystems.disks.{$disk}") === null) {
            return ProbeResult::skipped();
        }

        $started = hrtime(true);

        try {
            Storage::disk($disk)->exists('status/ping');

            return ProbeResult::ok((int) ((hrtime(true) - $started) / 1_000_000));
        } catch (Throwable $e) {
            return ProbeResult::fail('Falha: '.class_basename($e));
        }
    }
}

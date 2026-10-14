<?php

namespace App\Modules\Status\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Contingência estática: quando `platform.status.publish_disk` está definido, o retrato vai para `status/status.json` nesse
 * disco (CDN/bucket) a cada sondagem, de modo que a página continue consultável mesmo com a aplicação ou o banco fora do ar.
 */
class StatusPublisher
{
    /** @param array<string, mixed> $payload */
    public static function publish(array $payload): bool
    {
        $disk = config('platform.status.publish_disk');

        if (! is_string($disk) || $disk === '') {
            return false;
        }

        try {
            return Storage::disk($disk)->put('status/status.json', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'public');
        } catch (Throwable $e) {
            Log::warning('Status: não foi possível publicar o retrato estático.', ['erro' => class_basename($e)]);

            return false;
        }
    }
}

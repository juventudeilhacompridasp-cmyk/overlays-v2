<?php

namespace App\Modules\Portal\Push;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;

final class PushConfig
{
    /** As chaves VAPID estão configuradas? */
    public static function enabled(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key'));
    }

    /** Chave pública para o navegador assinar, ou null se o recurso está desligado para esta organização/instalação. */
    public static function publicKeyFor(): ?string
    {
        return self::enabled() && CurrentOrganization::has(Feature::Push) ? (string) config('webpush.public_key') : null;
    }
}

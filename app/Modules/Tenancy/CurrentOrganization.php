<?php

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Models\Organization;
use Closure;

/**
 * Guarda a organização "ativa" da requisição/job. Enquanto não houver multi-tenancy completa,
 * o middleware define a organização padrão. Jobs e comandos devem definir explicitamente.
 */
final class CurrentOrganization
{
    private static ?Organization $organization = null;

    public static function set(Organization $organization): void
    {
        self::$organization = $organization;
    }

    public static function get(): ?Organization
    {
        return self::$organization;
    }

    public static function id(): ?string
    {
        return self::$organization?->getKey();
    }

    public static function forget(): void
    {
        self::$organization = null;
    }

    /**
     * Executa o callback dentro de uma organização e restaura a anterior ao final.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function using(Organization $organization, Closure $callback): mixed
    {
        $previous = self::$organization;
        self::$organization = $organization;

        try {
            return $callback();
        } finally {
            self::$organization = $previous;
        }
    }
}

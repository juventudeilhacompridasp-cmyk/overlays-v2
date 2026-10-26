<?php

namespace App\Modules\Matches\Support;

/** Gera a evidência criptográfica que será gravada junto à assinatura da súmula. */
final class DocumentSignature
{
    public static function hash(string $document): string
    {
        return hash('sha256', $document);
    }
}

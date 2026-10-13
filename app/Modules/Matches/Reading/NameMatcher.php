<?php

namespace App\Modules\Matches\Reading;

use Illuminate\Support\Str;

/** Comparação aproximada de nomes lidos de súmula (acento, caixa e abreviações não podem gerar divergência falsa). */
final class NameMatcher
{
    public static function normalize(?string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $name))));
    }

    public static function same(?string $a, ?string $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        $tokensA = explode(' ', $a);
        $tokensB = explode(' ', $b);

        // "João" x "João Silva": os nomes de um estão todos no outro.
        if (array_diff($tokensA, $tokensB) === [] || array_diff($tokensB, $tokensA) === []) {
            return true;
        }

        similar_text($a, $b, $percent);

        return $percent >= 85;
    }
}

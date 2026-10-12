<?php

namespace App\Modules\Teams\Support;

/**
 * CPF: normalização, validação dos dígitos verificadores e hash para busca/unicidade.
 *
 * LGPD: o CPF nunca é gravado nem devolvido em claro. Guardamos só o HMAC-SHA256 dos 11 dígitos
 * (chave: `platform.document_hash_key`, que usa a APP_KEY quando não definida). Trocar a chave invalida
 * a busca por CPF dos cadastros existentes; por isso ela deve ser mantida estável.
 */
final class Cpf
{
    /** Só os dígitos do texto informado. */
    public static function normalize(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function isValid(string $value): bool
    {
        $digits = self::normalize($value);

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * ($length + 1 - $i);
            }

            $check = (($sum * 10) % 11) % 10;

            if ($check !== (int) $digits[$length]) {
                return false;
            }
        }

        return true;
    }

    public static function hash(string $value): string
    {
        $key = config('platform.document_hash_key') ?: config('app.key');

        return hash_hmac('sha256', self::normalize($value), (string) $key);
    }
}

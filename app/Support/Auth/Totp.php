<?php

namespace App\Support\Auth;

/**
 * Senha de uso único por tempo (TOTP, RFC 6238) com HMAC-SHA1, 6 dígitos e passo de 30 s: o padrão dos aplicativos de
 * autenticação (Google Authenticator, Authy, 1Password...). Puro e sem biblioteca; segredos em Base32 (RFC 4648).
 */
final class Totp
{
    public const STEP = 30;

    public const DIGITS = 6;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Segredo novo de 160 bits (32 caracteres Base32), o tamanho recomendado para HMAC-SHA1. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** Código vigente no instante `$timestamp` (ou `$step`, se informado: contador já dividido pelo passo). */
    public static function code(string $secret, ?int $timestamp = null, ?int $step = null, int $digits = self::DIGITS): string
    {
        $counter = $step ?? intdiv($timestamp ?? time(), self::STEP);
        $hash = hash_hmac('sha1', pack('J', $counter), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Confere o código aceitando `$window` passos para trás e para a frente (relógio do celular um pouco fora). Devolve o passo
     * em que bateu (para impedir reaproveitar o mesmo código) ou null.
     */
    public static function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return null;
        }

        $current = intdiv($timestamp ?? time(), self::STEP);
        $matched = null;

        // Percorre todos os passos (tempo constante entre acertos e erros) e fica com o mais recente que bateu.
        for ($step = $current - $window; $step <= $current + $window; $step++) {
            if (hash_equals(self::code($secret, step: $step), $code)) {
                $matched = $step;
            }
        }

        return $matched;
    }

    /** Endereço `otpauth://` que os aplicativos leem pelo QR code. */
    public static function uri(string $issuer, string $account, string $secret): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return "otpauth://totp/{$label}?secret={$secret}&issuer=".rawurlencode($issuer).'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::STEP;
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=-]/', '', $text) ?? '');
        $bits = '';

        foreach (str_split($text) as $char) {
            $value = strpos(self::ALPHABET, $char);

            if ($value === false) {
                continue;
            }

            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr((int) bindec($chunk));
            }
        }

        return $bytes;
    }
}

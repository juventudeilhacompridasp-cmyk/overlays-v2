<?php

namespace App\Support\Auth;

use App\Models\User;

/**
 * Regras da autenticação em dois fatores de um usuário: ativar (segredo novo + confirmação por código), conferir o código ou
 * um código de recuperação, gerar novos códigos de recuperação e desativar. Não há banco aqui além do próprio usuário.
 */
final class TwoFactor
{
    public const RECOVERY_CODES = 8;

    /** Começa a ativação: guarda um segredo novo, ainda NÃO confirmado (o login segue sem segundo fator). */
    public static function begin(User $user): string
    {
        $secret = Totp::generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return $secret;
    }

    /**
     * Confirma a ativação com um código do aplicativo. Devolve os códigos de recuperação em claro (mostrados uma única vez)
     * ou null se o código estiver errado ou não houver ativação em andamento.
     *
     * @return list<string>|null
     */
    public static function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
            return null;
        }

        $step = Totp::verify($user->two_factor_secret, $code);

        if ($step === null) {
            return null;
        }

        $codes = self::generateRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => self::hashAll($codes),
        ])->save();

        return $codes;
    }

    /** Confere o código do aplicativo (só vale uma vez por passo de 30 s). */
    public static function verifyCode(User $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        $step = Totp::verify((string) $user->two_factor_secret, $code);

        if ($step === null || ($user->two_factor_last_step !== null && $step <= (int) $user->two_factor_last_step)) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }

    /** Usa um código de recuperação (cada um vale uma vez). */
    public static function useRecoveryCode(User $user, string $code): bool
    {
        $hash = self::hash($code);
        $stored = $user->two_factor_recovery_codes ?? [];
        $found = array_search($hash, $stored, true);

        if (! $user->hasTwoFactorEnabled() || $found === false) {
            return false;
        }

        unset($stored[$found]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($stored)])->save();

        return true;
    }

    /**
     * Gera um conjunto novo de códigos de recuperação (os antigos deixam de valer).
     *
     * @return list<string>
     */
    public static function regenerateRecoveryCodes(User $user): array
    {
        $codes = self::generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => self::hashAll($codes)])->save();

        return $codes;
    }

    public static function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    public static function recoveryCodesLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /** @return list<string> */
    private static function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $codes[] = substr($raw = bin2hex(random_bytes(5)), 0, 5).'-'.substr($raw, 5, 5);
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private static function hashAll(array $codes): array
    {
        return array_map(fn (string $code): string => self::hash($code), $codes);
    }

    private static function hash(string $code): string
    {
        return hash('sha256', strtolower(preg_replace('/[\s-]/', '', $code) ?? ''));
    }
}

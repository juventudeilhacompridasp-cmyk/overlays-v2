<?php

namespace App\Support\Auth;

/**
 * Parâmetros da autenticação em dois fatores que são política da INSTALAÇÃO (variáveis de ambiente em `config/platform.php`).
 * O padrão é o comportamento original; valores fora da faixa voltam para o limite mais próximo.
 */
final class TwoFactorSettings
{
    /** Quantidade de códigos de recuperação gerados (4 a 16). */
    public static function recoveryCodes(): int
    {
        return self::between('recovery_codes', 8, 4, 16);
    }

    /** Minutos que a verificação do segundo fator vale depois de a senha ser aceita (1 a 60). */
    public static function challengeMinutes(): int
    {
        return self::between('challenge_minutes', 10, 1, 60);
    }

    /** Erros de código por minuto (por pessoa e IP) antes do bloqueio (1 a 20). */
    public static function maxAttempts(): int
    {
        return self::between('max_attempts', 5, 1, 20);
    }

    /** Tolerância do relógio, em passos de 30 s para cada lado (0 a 2). */
    public static function window(): int
    {
        return self::between('window', 1, 0, 2);
    }

    /** Se a plataforma manda e-mail de aviso ao ativar, desativar ou reiniciar (a organização ainda pode desligar em Comunicações). */
    public static function notify(): bool
    {
        return (bool) config('platform.two_factor.notify', true);
    }

    private static function between(string $key, int $default, int $min, int $max): int
    {
        $value = config('platform.two_factor.'.$key, $default);

        return max($min, min($max, is_numeric($value) ? (int) $value : $default));
    }
}

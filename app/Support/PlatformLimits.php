<?php

namespace App\Support;

/**
 * Limites de segurança da PLATAFORMA (não da organização): requisições por minuto de cada grupo de rotas públicas e validade
 * dos links assinados de arquivos privados. Valem para toda a instalação e vêm de `config/platform.php` (variáveis de ambiente);
 * o padrão é o comportamento original. Nunca abaixo de 1.
 */
final class PlatformLimits
{
    /** Requisições por minuto (por IP) de cada grupo, com o padrão original. */
    public const PER_MINUTE = [
        'api' => 60,           // API pública /api/v1
        'overlay' => 120,      // estado, fonte e dados dos overlays (o OBS consulta a cada poucos segundos)
        'embed' => 120,        // widgets em iframe
        'push' => 30,          // pedidos de aviso push
        'card' => 30,          // verificação da carteirinha (QR)
        'public' => 60,        // páginas públicas leves (status, busca, feed, inscrição)
        'registration' => 10,  // envio do pedido de inscrição
    ];

    /** Minutos de validade dos links assinados (arquivos privados: dado pessoal), com o padrão original. */
    public const SIGNED_MINUTES = [
        'documents' => 10, // documentos de inscrição e súmulas enviadas
        'photos' => 30,    // fotos de pessoas no painel
    ];

    public static function perMinute(string $group): int
    {
        return max(1, (int) config("platform.limits.{$group}", self::PER_MINUTE[$group] ?? 60));
    }

    /** Texto do middleware de limite de requisições (`throttle:N,1`) do grupo. */
    public static function throttle(string $group): string
    {
        return 'throttle:'.self::perMinute($group).',1';
    }

    public static function signedMinutes(string $kind): int
    {
        return max(1, (int) config("platform.signed_url_minutes.{$kind}", self::SIGNED_MINUTES[$kind] ?? 10));
    }
}

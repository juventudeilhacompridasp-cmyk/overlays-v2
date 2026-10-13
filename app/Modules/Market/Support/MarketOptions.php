<?php

namespace App\Modules\Market\Support;

/** Listas fixas do perfil do Mercado (as posições vêm do perfil de cada esporte, `SportProfile::$positions`). */
final class MarketOptions
{
    /** Idade mínima para entrar no Mercado (menores ficam de fora: ADR 0008). */
    public const MIN_AGE = 18;

    /** Dias até o perfil publicado precisar ser revalidado pelo atleta. */
    public const REVALIDATE_DAYS = 60;

    public const DAYS = ['mon' => 'Segunda', 'tue' => 'Terça', 'wed' => 'Quarta', 'thu' => 'Quinta', 'fri' => 'Sexta', 'sat' => 'Sábado', 'sun' => 'Domingo'];

    public const PERIODS = ['morning' => 'Manhã', 'afternoon' => 'Tarde', 'evening' => 'Noite'];

    public const LEVELS = ['recreational' => 'Recreativo', 'amateur' => 'Amador', 'semi_pro' => 'Semiprofissional'];

    public const FEET = ['right' => 'Direito', 'left' => 'Esquerdo', 'both' => 'Ambidestro'];

    public const CHANNELS = ['email' => 'E-mail', 'whatsapp' => 'WhatsApp'];

    /**
     * @param  array<string, string>  $options
     * @return list<array{value: string, label: string}>
     */
    public static function list(array $options): array
    {
        return array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys($options), array_values($options));
    }
}

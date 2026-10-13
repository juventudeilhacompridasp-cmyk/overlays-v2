<?php

namespace App\Modules\Market\Actions;

use App\Modules\Market\Enums\ProfileStatus;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Support\MarketOptions;
use InvalidArgumentException;

/**
 * Publica o perfil no Mercado — ou o revalida (renova o prazo de 60 dias de um perfil já publicado). Exige e-mail
 * verificado, maioridade e o mínimo para o gestor avaliar: esporte, posição, cidade e canal de contato.
 */
final class PublishMarketProfile
{
    public function handle(MarketProfile $profile): MarketProfile
    {
        // Contratado pode voltar ao Mercado se ficar sem equipe; suspenso só a organização reativa.
        if ($profile->status === ProfileStatus::Suspended) {
            throw new InvalidArgumentException('Este perfil está suspenso pela organização.');
        }

        if (! $profile->user->hasVerifiedEmail()) {
            throw new InvalidArgumentException('Confirme o seu e-mail para publicar o perfil.');
        }

        if ($profile->age() < MarketOptions::MIN_AGE) {
            throw new InvalidArgumentException('O Mercado é só para maiores de '.MarketOptions::MIN_AGE.' anos.');
        }

        $missing = array_keys(array_filter([
            'esporte' => blank($profile->sport),
            'posição' => blank($profile->positions),
            'cidade' => blank($profile->city),
            'canal de contato' => blank($profile->contact_channel) || blank($profile->contact_value),
        ]));

        if ($missing !== []) {
            throw new InvalidArgumentException('Complete o perfil antes de publicar: '.implode(', ', $missing).'.');
        }

        $profile->forceFill([
            'status' => ProfileStatus::Published,
            'published_at' => $profile->status === ProfileStatus::Published ? $profile->published_at : now(),
            'expires_at' => now()->addDays(MarketOptions::REVALIDATE_DAYS),
            'expiry_notice_at' => null,
        ])->save();

        return $profile;
    }
}

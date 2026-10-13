<?php

namespace App\Modules\Market\Actions;

use App\Modules\Market\Models\MarketProfile;

/** Grava os dados do perfil do atleta (a situação e a foto têm ações próprias). A validação é do FormRequest/controlador. */
final class SaveMarketProfile
{
    private const FIELDS = [
        'sport', 'positions', 'dominant_foot', 'height_cm', 'level', 'bio', 'availability',
        'city', 'neighborhood', 'display_name', 'contact_channel', 'contact_value',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(MarketProfile $profile, array $data): MarketProfile
    {
        $profile->fill(array_intersect_key($data, array_flip(self::FIELDS)))->save();

        return $profile;
    }
}

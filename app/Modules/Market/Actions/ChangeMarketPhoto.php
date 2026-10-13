<?php

namespace App\Modules\Market\Actions;

use App\Modules\Market\Models\MarketProfile;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;

/**
 * Troca (ou remove, com null) a foto do perfil. Foto é dado pessoal: disco PRIVADO `s3-private`, só por URL assinada,
 * WebP quadrado de 400 e 96 px (sem EXIF/GPS). Nunca vai ao portal público.
 */
final class ChangeMarketPhoto
{
    public const DISK = 's3-private';

    public const SIZES = ['full' => 400, 'thumb' => 96];

    public function handle(MarketProfile $profile, ?UploadedFile $file): MarketProfile
    {
        $old = $profile->photo_path;
        $new = null;

        if ($file !== null) {
            $new = ImageStore::save(self::DISK, "organizations/{$profile->organization_id}/market/{$profile->getKey()}", 'photo', $file, [
                'full' => ['size' => self::SIZES['full'], 'fit' => 'cover'],
                'thumb' => ['size' => self::SIZES['thumb'], 'fit' => 'cover'],
            ]);
        }

        $profile->forceFill(['photo_path' => $new])->save();
        ImageStore::delete(self::DISK, $old, array_values(self::SIZES));

        return $profile;
    }
}

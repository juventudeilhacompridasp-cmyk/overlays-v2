<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Teams\Models\Team;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;

/**
 * Troca (ou remove, com null) o escudo da equipe. Fica no disco público `s3` (CDN) em WebP de 512 px e 128 px,
 * preservando a transparência. O arquivo anterior só é apagado depois de o novo estar salvo.
 */
final class ChangeTeamLogo
{
    public const DISK = 's3';

    public const SIZES = ['full' => 512, 'thumb' => 128];

    public function handle(Team $team, ?UploadedFile $file): Team
    {
        $old = $team->logo_path;
        $new = null;

        if ($file !== null) {
            $new = ImageStore::save(self::DISK, "organizations/{$team->organization_id}/teams/{$team->getKey()}", 'logo', $file, [
                'full' => ['size' => self::SIZES['full'], 'fit' => 'contain'],
                'thumb' => ['size' => self::SIZES['thumb'], 'fit' => 'contain'],
            ]);
        }

        $team->forceFill(['logo_path' => $new])->save();
        ImageStore::delete(self::DISK, $old, array_values(self::SIZES));

        return $team;
    }
}

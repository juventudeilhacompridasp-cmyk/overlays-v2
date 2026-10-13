<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Organization;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;

/** Troca (ou remove, com null) o logotipo da organização (marca própria). Disco público, WebP 512/128 px, com transparência. */
final class ChangeOrganizationLogo
{
    public const DISK = 's3';

    public const SIZES = ['full' => 512, 'thumb' => 128];

    public function handle(Organization $organization, ?UploadedFile $file): Organization
    {
        $old = $organization->logo_path;
        $new = null;

        if ($file !== null) {
            $new = ImageStore::save(self::DISK, "organizations/{$organization->getKey()}/brand", 'logo', $file, [
                'full' => ['size' => self::SIZES['full'], 'fit' => 'contain'],
                'thumb' => ['size' => self::SIZES['thumb'], 'fit' => 'contain'],
            ]);
        }

        $organization->forceFill(['logo_path' => $new])->save();
        ImageStore::delete(self::DISK, $old, array_values(self::SIZES));

        return $organization;
    }

    public static function url(Organization $organization, int $size = 128): ?string
    {
        return ImageStore::publicUrl($organization->logo_path, $size);
    }
}

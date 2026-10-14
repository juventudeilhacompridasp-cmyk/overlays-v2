<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlaySponsor;
use App\Modules\Tenancy\CurrentOrganization;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Cria ou edita um patrocinador. O logotipo é reencodado em WebP (512 e 128 px, transparência preservada, sem EXIF) no
 * disco público; o anterior só é apagado depois de o novo estar salvo. Toda mudança atualiza os canais abertos no OBS.
 */
final class SaveOverlaySponsor
{
    private const DISK = 's3';

    public function handle(?OverlaySponsor $sponsor, string $name, bool $active, ?UploadedFile $logo, ?UploadedFile $banner = null, bool $removeBanner = false): OverlaySponsor
    {
        if ($sponsor === null && $logo === null) {
            throw new InvalidArgumentException('Envie o logotipo do patrocinador.');
        }

        if ($sponsor === null && OverlaySponsor::query()->count() >= OverlaySponsor::LIMIT) {
            throw new InvalidArgumentException('A organização já tem '.OverlaySponsor::LIMIT.' patrocinadores. Exclua algum para cadastrar outro.');
        }

        $old = $sponsor?->logo_path;
        $path = $old;

        if ($logo !== null) {
            $organizationId = $sponsor !== null ? $sponsor->organization_id : CurrentOrganization::id();
            $path = ImageStore::save(self::DISK, "organizations/{$organizationId}/sponsors", 'logo', $logo, [
                'full' => ['size' => OverlaySponsor::SIZES['full'], 'fit' => 'contain'],
                'thumb' => ['size' => OverlaySponsor::SIZES['thumb'], 'fit' => 'contain'],
            ]);
        }

        $oldBanner = $sponsor?->banner_path;
        $bannerPath = $removeBanner ? null : $oldBanner;

        if ($banner !== null) {
            $organizationId ??= $sponsor !== null ? $sponsor->organization_id : CurrentOrganization::id();
            $bannerPath = ImageStore::save(self::DISK, "organizations/{$organizationId}/sponsors", 'banner', $banner, [
                'wide' => ['size' => OverlaySponsor::BANNER_SIZE, 'fit' => 'contain'],
            ]);
        }

        $sponsor ??= new OverlaySponsor(['sort' => (int) OverlaySponsor::query()->max('sort') + 1]);
        $sponsor->fill(['name' => $name, 'active' => $active, 'logo_path' => $path, 'banner_path' => $bannerPath])->save();

        if ($logo !== null) {
            ImageStore::delete(self::DISK, $old, array_values(OverlaySponsor::SIZES));
        }

        if ($oldBanner !== null && $oldBanner !== $bannerPath) {
            ImageStore::delete(self::DISK, $oldBanner, [OverlaySponsor::BANNER_SIZE]);
        }

        self::refresh();

        return $sponsor;
    }

    public function delete(OverlaySponsor $sponsor): void
    {
        ImageStore::delete(self::DISK, $sponsor->logo_path, array_values(OverlaySponsor::SIZES));
        ImageStore::delete(self::DISK, $sponsor->banner_path, [OverlaySponsor::BANNER_SIZE]);
        $sponsor->delete();

        self::refresh();
    }

    /** Os canais abertos no OBS recebem a nova lista sem recarregar. */
    private static function refresh(): void
    {
        OverlayChannel::query()->get()->each(fn (OverlayChannel $channel) => OverlayUpdated::dispatchFor($channel));
    }
}

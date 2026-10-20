<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Models\OverlayCustomAsset;
use App\Modules\Tenancy\CurrentOrganization;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/** Guarda imagens do Builder sem EXIF e em WebP, reduzindo o peso da fonte de navegador no OBS. */
final class StoreOverlayCustomAsset
{
    public function handle(UploadedFile $file, string $name): OverlayCustomAsset
    {
        if (OverlayCustomAsset::query()->count() >= OverlayCustomAsset::LIMIT) {
            throw new InvalidArgumentException('A organização já tem '.OverlayCustomAsset::LIMIT.' imagens do Builder. Exclua alguma para enviar outra.');
        }

        $organizationId = CurrentOrganization::id();
        $path = ImageStore::save('s3', "organizations/{$organizationId}/overlay-assets", 'image', $file, [
            'full' => ['size' => OverlayCustomAsset::SIZE, 'fit' => 'contain'],
        ]);

        return OverlayCustomAsset::query()->create([
            'name' => mb_substr(trim($name) !== '' ? trim($name) : 'Imagem', 0, 80),
            'path' => $path,
            'variant_size' => OverlayCustomAsset::SIZE,
        ]);
    }

    public function delete(OverlayCustomAsset $asset): void
    {
        ImageStore::delete('s3', $asset->path, [$asset->variant_size]);
        $asset->delete();
    }
}
